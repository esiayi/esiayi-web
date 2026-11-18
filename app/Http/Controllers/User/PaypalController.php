<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Mail\OrderSuccessfully;
use App\Helpers\MailHelper;
use App\Models\EmailTemplate;
use App\Models\PaypalPayment;
use App\Models\BreadcrumbImage;
use App\Models\Service;
use App\Models\AdditionalService;
use App\Models\Order;
use App\Models\Setting;
use App\Models\AppointmentSchedule;
use App\Models\CouponHistory;
use App\Services\BookingPricing;
use App\Services\PayPalCheckout;
use Illuminate\Support\Facades\Cache;

use Str;
use Cart;
use Mail;
use Session;
use Auth;


class PaypalController extends Controller
{
    public function payWithPaypal($slug){
        if(env('APP_MODE') == 'DEMO'){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }
        $service = Service::where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();
        if(!$service){
            abort(404);
        }

        $order_info = $this->sessionOrder($service);
        if(!$order_info){
            $notification = trans('user.Please select a schedule');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('ready-to-booking', $service->slug)->with($notification);
        }

        $paypalSetting = PaypalPayment::first();
        $amount = $this->payableAmount($service, $order_info, $paypalSetting);

        try {
            $paypal = PayPalCheckout::fromSettings();
            $paypalOrder = $paypal->createOrder($amount, route('paypal-payment-success'), route('paypal-payment-cancled'), (string) config('app.name'));
        } catch (\Exception $ex) {
            $notification = trans('user_validation.Payment Faild');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        // Remember exactly what this PayPal order pays for, so the booking created on return
        // is the one priced here even if the customer changes the booking in another tab.
        Session::put('service', $service);
        Session::put('paypal_checkout', [
            'order_id' => $paypalOrder['id'],
            'amount' => $amount,
            'currency' => $paypal->currency(),
            'order_info' => $order_info,
        ]);

        return redirect($paypalOrder['approve_url']);
    }

    public function paypalPaymentSuccess(Request $request){

        Session::put('return_from_mollie','payment_faild');
        $session_service = Session::get('service');
        $service = $session_service ? Service::where(['slug' => $session_service->slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first() : null;
        $checkout = Session::get('paypal_checkout');

        $failed = array('messege'=>trans('user_validation.Payment Faild'),'alert-type'=>'error');
        $paypal_order_id = (string) $request->query('token');

        // PayPal returns ?token={order id}; it must be the order this session created.
        if(!$service || !is_array($checkout) || !$paypal_order_id || $paypal_order_id !== $checkout['order_id']
            || ($checkout['order_info']->service_id ?? null) != $service->id){
            return $service ? redirect()->route('payment', $service->slug)->with($failed) : redirect()->route('home')->with($failed);
        }

        try {
            // One request at a time per PayPal order, so a double submit cannot book twice.
            return Cache::lock('paypal-order:'.$paypal_order_id, 60)->block(15, function () use ($service, $checkout, $paypal_order_id, $failed) {
                $placed = array('messege'=>trans('user_validation.Your order has been placed. Thanks for your new order'),'alert-type'=>'success');
                if(BookingPricing::transactionUsed('Paypal', $paypal_order_id)){
                    return redirect()->route('dashboard')->with($placed);
                }

                $paypal = PayPalCheckout::fromSettings();

                // Check the order before capturing it, while no money has moved.
                $order = $paypal->getOrder($paypal_order_id);
                if(!in_array($order['status'], ['APPROVED', 'COMPLETED'], true) || $order['currency'] !== $checkout['currency']
                    || !BookingPricing::amountCovers($order['amount'], $checkout['amount'])){
                    return redirect()->route('payment', $service->slug)->with($failed);
                }

                $capture = $paypal->captureOrder($paypal_order_id);
                if($capture['currency'] !== $checkout['currency'] || !BookingPricing::amountCovers($capture['amount'], $checkout['amount'])){
                    $paypal->refundCapture($capture['capture_id']);
                    return redirect()->route('payment', $service->slug)->with($failed);
                }

                $user = Auth::guard('web')->user();
                // A PENDING capture is paid but held by PayPal; admins approve it like a bank transfer.
                $payment_status = $capture['status'] === 'COMPLETED' ? 'success' : 'pending';
                $order = $this->createOrder($user, $service, $checkout['order_info'], $service->provider_id, $user->id, 'Paypal', $payment_status, $paypal_order_id);

                $this->sendMailToClient($user, $order);
                $this->sendMailToProvider($service->provider, $order);

                Session::forget(['order_info', 'return_from_mollie', 'service', 'paypal_checkout']);

                return redirect()->route('dashboard')->with($placed);
            });
        } catch (\Exception $ex) {
            return redirect()->route('payment', $service->slug)->with($failed);
        }
    }

    public function paypalPaymentCancled(){
        Session::put('return_from_mollie','payment_faild');
        $service = Session::get('service');

        $notification = trans('user_validation.Payment Faild');
        $notification = array('messege'=>$notification,'alert-type'=>'error');
        if(!$service){
            return redirect()->route('home')->with($notification);
        }
        return redirect()->route('payment', $service->slug)->with($notification);
    }

    private function sessionOrder($service){
        $order_info = Session::get('order_info');
        if(!is_object($order_info) || ($order_info->service_id ?? null) != $service->id){
            return null;
        }

        return $order_info;
    }

    private function payableAmount($service, $order_info, $paypalSetting){
        return round(BookingPricing::frozenTotals($service, $order_info)->total * $paypalSetting->currency_rate, 2);
    }


    public function createOrder($user, $service, $order_info, $provider_id, $client_id, $payment_method, $payment_status, $tnx_info){

        $extra_services = json_decode($order_info->extras);
        $additional_amount = $order_info->extra_price;
        $additional_services = array();
        if($extra_services->ids){
            foreach($extra_services->ids as $index => $extra_service){
                $single_extra_service = array(
                    'service_name' => $extra_services->names[$index],
                    'qty' => $extra_services->quantities[$index],
                    'price' => $extra_services->prices[$index],
                );
                $additional_services[] = $single_extra_service;
            }
        }


        $order_additional_services = json_encode($additional_services);
        $order_note = $order_info->customer->order_note;
        $client_address = $order_info->customer;

        $find_schedule = AppointmentSchedule::find($order_info->schedule_time_slot);
        $time_slot = '';
        if($find_schedule){
            $time_slot = strtoupper(date('h:i A', strtotime($find_schedule->start_time))).' - '.strtoupper(date('h:i A', strtotime($find_schedule->end_time)));
        }

        $totals = BookingPricing::frozenTotals($service, $order_info);
        $coupon = !empty($totals->coupon_code) ? \App\Models\Coupon::where('coupon_code', $totals->coupon_code)->first() : null;

        if($coupon){
            $history = new CouponHistory();
            $history->user_id = $client_id;
            $history->provider_id = $coupon->provider_id;
            $history->coupon_code = $coupon->coupon_code;
            $history->coupon_id = $coupon->id;
            $history->discount_amount = $totals->discount;
            $history->save();
        }

        $order = new Order();
        $order->order_id = substr(rand(0,time()),0,10);
        $order->booking_date = $order_info->date;
        $order->appointment_schedule_id = $order_info->schedule_time_slot;
        $order->schedule_time_slot = $time_slot;
        $order->client_id = $client_id;
        $order->provider_id = $provider_id;
        $order->service_id = $service->id;
        $order->package_amount = $service->price;
        $order->additional_amount = $additional_amount;
        $order->coupon_discount = $totals->discount;
        $order->total_amount = $totals->total;
        $order->payment_method = $payment_method;
        $order->transection_id = $tnx_info;
        $order->payment_status = $payment_status;
        $order->order_status = 'awaiting_for_provider_approval';
        $order->package_features = $service->package_features;
        $order->additional_services = $order_additional_services;
        $order->order_note = $order_note;
        $order->client_address = json_encode($client_address);
        $order->save();

        Session::forget('coupon_code');
        Session::forget('offer_percentage');

        return $order;
    }


    public function sendMailToClient($user, $order){
        MailHelper::setMailConfig();

        $setting = Setting::first();

        $template=EmailTemplate::where('id',8)->first();
        $subject=$template->subject;
        $message=$template->description;
        $message = str_replace('{{name}}',$user->name,$message);
        $message = str_replace('{{amount}}',$setting->currency_icon.$order->total_amount,$message);
        $message = str_replace('{{schedule_date}}',$order->booking_date,$message);
        $message = str_replace('{{order_id}}',$order->order_id,$message);
        Mail::to($user->email)->send(new OrderSuccessfully($message,$subject));
    }

    public function sendMailToProvider($provider, $order){
        MailHelper::setMailConfig();

        $setting = Setting::first();

        $template=EmailTemplate::where('id',9)->first();
        $subject=$template->subject;
        $message=$template->description;
        $message = str_replace('{{name}}',$provider->name,$message);
        $message = str_replace('{{amount}}',$setting->currency_icon.$order->total_amount,$message);
        $message = str_replace('{{schedule_date}}',$order->booking_date,$message);
        $message = str_replace('{{order_id}}',$order->order_id,$message);
        Mail::to($provider->email)->send(new OrderSuccessfully($message,$subject));
    }


}
