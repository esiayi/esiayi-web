<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\PaymentExecution;

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

use Str;
use Cart;
use Mail;
use Session;
use Auth;


class PaypalController extends Controller
{
    private $apiContext;
    public function __construct()
    {
        $account = PaypalPayment::first();
        $paypal_conf = \Config::get('paypal');
        $this->apiContext = new ApiContext(new OAuthTokenCredential(
            $account->client_id,
            $account->secret_id,
            )
        );

        $setting=array(
            'mode' => $account->account_mode,
            'http.ConnectionTimeOut' => 30,
            'log.LogEnabled' => true,
            'log.FileName' => storage_path() . '/logs/paypal.log',
            'log.LogLevel' => 'ERROR'
        );
        $this->apiContext->setConfig($setting);
    }


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
            $notification = trans('user_validation.Please select a schedule');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('ready-to-booking', $service->slug)->with($notification);
        }

        $paypalSetting = PaypalPayment::first();
        $payableAmount = $this->payableAmount($service, $order_info, $paypalSetting);

        $name=env('APP_NAME');

        // set payer
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");

        // set amount total
        $amount = new Amount();
        $amount->setCurrency($paypalSetting->currency_code)
            ->setTotal($payableAmount);

        // transaction
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setDescription(env('APP_NAME'));

        // redirect url
        $redirectUrls = new RedirectUrls();

        $root_url=url('/');
        $redirectUrls->setReturnUrl(route('paypal-payment-success'))
            ->setCancelUrl(route('paypal-payment-cancled'));

        // payment
        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction));
        try {
            $payment->create($this->apiContext);
        } catch (\PayPal\Exception\PPConnectionException $ex) {

            $notification = trans('user_validation.Payment Faild');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        // get paymentlink
        $approvalUrl = $payment->getApprovalLink();

        Session::put('service', $service);

        return redirect($approvalUrl);
    }

    public function paypalPaymentSuccess(Request $request){

        Session::put('return_from_mollie','payment_faild');
        $session_service = Session::get('service');
        $service = $session_service ? Service::where(['slug' => $session_service->slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first() : null;
        $order_info = $service ? $this->sessionOrder($service) : null;

        $failed = array('messege'=>trans('user_validation.Payment Faild'),'alert-type'=>'error');
        if(!$order_info){
            return redirect()->route('home')->with($failed);
        }

        if (empty($request->get('PayerID')) || empty($request->get('token')) || empty($request->get('paymentId'))) {
            return redirect()->route('payment', $service->slug)->with($failed);
        }

        $payment_id=$request->get('paymentId');
        if(BookingPricing::transactionUsed('Paypal', $payment_id)){
            return redirect()->route('payment', $service->slug)->with($failed);
        }

        try {
            $payment = Payment::get($payment_id, $this->apiContext);

            // Only execute a payment that was created for this booking's amount.
            $paypalSetting = PaypalPayment::first();
            $transactions = $payment->getTransactions();
            $paid_amount = $transactions ? $transactions[0]->getAmount()->getTotal() : null;
            if(!BookingPricing::amountCovers($paid_amount, $this->payableAmount($service, $order_info, $paypalSetting))){
                return redirect()->route('payment', $service->slug)->with($failed);
            }

            $execution = new PaymentExecution();
            $execution->setPayerId($request->get('PayerID'));
            /**Execute the payment **/
            $result = $payment->execute($execution, $this->apiContext);
        } catch (\Exception $ex) {
            return redirect()->route('payment', $service->slug)->with($failed);
        }

        if ($result->getState() != 'approved') {
            return redirect()->route('payment', $service->slug)->with($failed);
        }

        $user = Auth::guard('web')->user();
        $provider_id = $service->provider_id;
        $client_id = $user->id;

        $order = $this->createOrder($user, $service, $order_info, $provider_id, $client_id, 'Paypal', 'success', $payment_id);

        $provider = $service->provider;
        $this->sendMailToClient($user, $order);
        $this->sendMailToProvider($provider, $order);

        Session::forget('order_info');
        Session::forget('return_from_mollie');
        Session::forget('service');

        $notification = trans('user_validation.Your order has been placed. Thanks for your new order');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->route('dashboard')->with($notification);
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
        $total = BookingPricing::totals($service, $order_info->extra_price, BookingPricing::sessionCoupon($service))->total;
        return round($total * $paypalSetting->currency_rate, 2);
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

        $coupon = BookingPricing::sessionCoupon($service);
        $totals = BookingPricing::totals($service, $additional_amount, $coupon);

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
