<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


use App\Models\Setting;
use App\Models\StripePayment;
use App\Models\PaypalPayment;
use App\Mail\OrderSuccessfully;
use App\Helpers\MailHelper;
use App\Models\EmailTemplate;
use App\Models\RazorpayPayment;
use App\Models\Flutterwave;
use App\Models\PaystackAndMollie;
use App\Models\InstamojoPayment;
use App\Models\BankPayment;
use App\Models\AppointmentSchedule;


use Mail;
Use Stripe;
use Cart;
use Session;
use Str;
use Razorpay\Api\Api;
use Exception;
use Redirect;

use Mollie\Laravel\Facades\Mollie;


use App\Models\BreadcrumbImage;
use App\Models\Service;
use App\Models\AdditionalService;
use App\Models\Order;
use App\Models\Schedule;
use App\Models\Coupon;
use App\Models\CouponHistory;
use Auth;
use App\Services\BookingPricing;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web');
    }

    public function apply_coupon(Request $request){

        $order_info = Session::get('order_info');
        $service = is_object($order_info) ? Service::find($order_info->service_id ?? null) : null;
        if(!$service){
            $notification = trans('user_validation.Invalid coupon');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $coupon = Coupon::where(['coupon_code' => $request->coupon, 'status' => 1])->first();

        if(!$coupon){
            $notification = trans('user_validation.Invalid coupon');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        if($coupon->expired_date < date('Y-m-d')){
            $notification = trans('user_validation.Coupon already expired');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        // The provider comes from the booking being paid for, never from the request.
        if(!BookingPricing::validCoupon($service, $coupon->coupon_code)){
            $notification = trans('user_validation.You can not apply another provider coupon');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        Session::put('coupon_code', $coupon->coupon_code);
        Session::put('offer_percentage', $coupon->offer_percentage);

        $notification = trans('user_validation.Coupon applied successful');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->back()->with($notification);

    }

    public function ready_to_booking($slug){
        $breadcrumb = BreadcrumbImage::where(['id' => 8])->first();
        $service = Service::with('category','provider')->where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();

        if(!$service){
            abort(404);
        }


        $what_you_will_get = array();
        if($service->what_you_will_provide){
            $provides = json_decode($service->what_you_will_provide);
            foreach($provides as $provide){
                $what_you_will_get [] = $provide;
            }
        }

        $benifits = array();
        if($service->benifit){
            $exist_benifits = json_decode($service->benifit);
            foreach($exist_benifits as $exist_benifit){
                $benifits [] = $exist_benifit;
            }
        }

        $package_features = array();
        if($service->package_features){
            $features = json_decode($service->package_features);
            foreach($features as $feature){
                $package_features [] = $feature;
            }
        }

        $additional_services = AdditionalService::where('service_id', $service->id)->get();
        $available_days = AppointmentSchedule::where(['user_id' => $service->provider_id, 'status' => 1])->distinct()->pluck('day');

        $setting = Setting::first();
        $currency_icon = (object) array('icon' => $setting->currency_icon);

        $selected_theme = Session::get('selected_theme');
        if ($selected_theme == 'theme_one'){
            $active_theme = 'layout';
        }elseif($selected_theme == 'theme_two'){
            $active_theme = 'layout2';
        }elseif($selected_theme == 'theme_three'){
            $active_theme = 'layout3';
        }else{
            $active_theme = 'layout';
        }

        return view('ready_to_booking')->with([
            'active_theme' => $active_theme,
            'breadcrumb' => $breadcrumb,
            'service' => $service,
            'package_features' => $package_features,
            'benifits' => $benifits,
            'additional_services' => $additional_services,
            'available_days' => $available_days,
            'currency_icon' => $currency_icon,
            'what_you_will_get' => $what_you_will_get,
        ]);
    }

    public function get_available_schedule(Request $request){
        $html = "<option value=''>".trans('user_validation.Select')."</option>";

        $timestamp = $request->date ? strtotime($request->date) : false;
        if($timestamp === false || $timestamp < strtotime('today')){
            return response()->json(['is_available' => false, 'available_schedules' => $html]);
        }

        $day = date('l', $timestamp);
        $appointment_schedules = AppointmentSchedule::where(['user_id' => $request->provider_id, 'day' => $day, 'status' => 1])->get();
        // Skip slots saved with an end time before the start time.
        $appointment_schedules = $appointment_schedules->filter(fn ($schedule) => strtotime($schedule->end_time) > strtotime($schedule->start_time));

        $available_schedule_arr = array();
        foreach($appointment_schedules as $appointment_schedule){
            $exist = BookingPricing::slotTaken($request->provider_id, $appointment_schedule->id, $request->date);
            if($exist == 0){
                $available_schedule_arr[] = $appointment_schedule->id;
            }
        }

        $available_schedules = AppointmentSchedule::whereIn('id', $available_schedule_arr)->orderBy('start_time','asc')->get();

        $is_available = $available_schedules->count() > 0 ? true : false;
        foreach($available_schedules as $index=> $schedule){
            $html.='<option value="'.$schedule->id.'">'.strtoupper(date('h:i A', strtotime($schedule->start_time))).' - '.strtoupper(date('h:i A', strtotime($schedule->end_time))).'</option>';
        }

        return response()->json(['is_available' => $is_available, 'available_schedules' => $html]);
    }

    public function booking_information(Request $request, $slug){
        $breadcrumb = BreadcrumbImage::where(['id' => 8])->first();
        $service = Service::with('category','provider')->where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();

        if(!$service){
            abort(404);
        }

        $priced = BookingPricing::extras($service, $request->ids, $request->quantities);
        $booking_date = BookingPricing::slot($service, $request->date, $request->schedule_time_slot);
        $totals = BookingPricing::totals($service, $priced->extra_price);

        $extras = (object) array(
            'ids' => $priced->ids,
            'prices' => $priced->prices,
            'quantities' => $priced->quantities,
            'names' => $priced->names,
            'extra_total' => $totals->extra_price,
            'sub_total' => $totals->package_price,
            'total' => $totals->subtotal,
            'date' => $booking_date,
            'schedule_time_slot' => $request->schedule_time_slot,
        );

        $what_you_will_get = array();
        if($service->what_you_will_provide){
            $provides = json_decode($service->what_you_will_provide);
            foreach($provides as $provide){
                $what_you_will_get [] = $provide;
            }
        }

        $benifits = array();
        if($service->benifit){
            $exist_benifits = json_decode($service->benifit);
            foreach($exist_benifits as $exist_benifit){
                $benifits [] = $exist_benifit;
            }
        }

        $package_features = array();
        if($service->package_features){
            $features = json_decode($service->package_features);
            foreach($features as $feature){
                $package_features [] = $feature;
            }
        }

        $setting = Setting::first();
        $currency_icon = (object) array('icon' => $setting->currency_icon);

        $selected_theme = Session::get('selected_theme');
        if ($selected_theme == 'theme_one'){
            $active_theme = 'layout';
        }elseif($selected_theme == 'theme_two'){
            $active_theme = 'layout2';
        }elseif($selected_theme == 'theme_three'){
            $active_theme = 'layout3';
        }else{
            $active_theme = 'layout';
        }

        return view('booking_information')->with([
            'active_theme' => $active_theme,
            'breadcrumb' => $breadcrumb,
            'service' => $service,
            'package_features' => $package_features,
            'benifits' => $benifits,
            'what_you_will_get' => $what_you_will_get,
            'extra_services' => $extras,
            'extras' => json_encode($extras),
            'currency_icon' => $currency_icon,
            'user' => Auth::guard('web')->user(),
        ]);
    }


    public function payment(Request $request ,$slug){
        $user = Auth::guard('web')->user();
        if($user->is_provider == 1){
            $notification = trans('user_validation.You are log-in as a provider, you can not book any service');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $service = Service::with('category','provider')->where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();
        if(!$service){
            abort(404);
        }

        // Customer details arrive by POST (so they never end up in URLs, logs or analytics),
        // get priced and validated once, then the page is shown from the session.
        if($request->isMethod('post')){
            $rules = [
                'name'=>'required|max:255',
                'email'=>'nullable|email|max:255',
                'phone'=>'required|max:30',
                'address'=>'required|max:500',
                'post_code'=>'nullable|max:20',
                'order_note'=>'nullable|max:2000',
                'agree_with'=>'required',
                'extras'=>'required',
            ];
            $customMessages = [
                'name.required' => trans('user_validation.Name is required'),
                'email.required' => trans('user_validation.Email is required'),
                'phone.required' => trans('user_validation.Phone is required'),
                'address.required' => trans('user_validation.Address is required'),
                'agree_with.required' => trans('user_validation.Agree with terms and conditions is required'),
            ];
            $this->validate($request, $rules,$customMessages);

            $extra_services = json_decode(html_decode($request->extras));
            $priced = BookingPricing::extras($service, $extra_services->ids ?? [], $extra_services->quantities ?? []);
            $booking_date = BookingPricing::slot($service, $extra_services->date ?? null, $extra_services->schedule_time_slot ?? null);
            $totals = BookingPricing::totals($service, $priced->extra_price);

            $customer = (object) array(
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'post_code' => $request->post_code,
                'order_note' => $request->order_note,
            );

            $extras = json_encode(array(
                'ids' => $priced->ids,
                'prices' => $priced->prices,
                'quantities' => $priced->quantities,
                'names' => $priced->names,
                'extra_total' => $totals->extra_price,
                'sub_total' => $totals->package_price,
                'total' => $totals->subtotal,
                'date' => $booking_date,
                'schedule_time_slot' => $extra_services->schedule_time_slot,
            ));

            $order_info = (object) array(
                'service_id' => $service->id,
                'customer' => $customer,
                'extras' => $extras,
                'what_you_will_get' => $this->decodeList($service->what_you_will_provide),
                'benifits' => $this->decodeList($service->benifit),
                'package_features' => $this->decodeList($service->package_features),
                'extra_price' => $totals->extra_price,
                'total_price' => $totals->subtotal,
                'package_price' => $totals->package_price,
                'date' => $booking_date,
                'schedule_time_slot' => $extra_services->schedule_time_slot,
            );

            Session::put('order_info', $order_info);

            return redirect()->route('payment', $service->slug);
        }

        $order_info = $this->sessionOrder($service);
        if(!$order_info){
            $notification = trans('user_validation.Please select a schedule');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('ready-to-booking', $service->slug)->with($notification);
        }

        $breadcrumb = BreadcrumbImage::where(['id' => 8])->first();
        $setting = Setting::first();
        $currency_icon = (object) array('icon' => $setting->currency_icon);
        $coupon = BookingPricing::sessionCoupon($service);
        $totals = BookingPricing::totals($service, $order_info->extra_price, $coupon);

        // Freeze the amount shown here; gateways are checked against it and orders record it.
        $order_info->totals = (array) $totals + ['coupon_code' => $coupon->coupon_code ?? null];
        Session::put('order_info', $order_info);

        $bankPayment = BankPayment::select('id','status','account_info','image')->first();
        $stripe = StripePayment::first();
        $paypal = PaypalPayment::first();
        $razorpay = RazorpayPayment::first();
        $flutterwave = Flutterwave::first();
        $mollie = PaystackAndMollie::first();
        $paystack = $mollie;
        $instamojoPayment = InstamojoPayment::first();

        $selected_theme = Session::get('selected_theme');
        if ($selected_theme == 'theme_one'){
            $active_theme = 'layout';
        }elseif($selected_theme == 'theme_two'){
            $active_theme = 'layout2';
        }elseif($selected_theme == 'theme_three'){
            $active_theme = 'layout3';
        }else{
            $active_theme = 'layout';
        }

        return view('payment')->with([
            'active_theme' => $active_theme,
            'breadcrumb' => $breadcrumb,
            'service' => $service,
            'customer' => $order_info->customer,
            'package_features' => $order_info->package_features,
            'benifits' => $order_info->benifits,
            'what_you_will_get' => $order_info->what_you_will_get,
            'extra_services' => json_decode($order_info->extras),
            'currency_icon' => $currency_icon,
            'bankPayment' => $bankPayment,
            'stripe' => $stripe,
            'paypal' => $paypal,
            'razorpay' => $razorpay,
            'total_price' => $totals->subtotal,
            'coupon_discount' => $totals->discount,
            'flutterwave' => $flutterwave,
            'user' => $user,
            'mollie' => $mollie,
            'paystack' => $paystack,
            'instamojoPayment' => $instamojoPayment,
        ]);
    }

    /**
     * The booking prepared by payment() for this service, if there is one in the session.
     */
    private function sessionOrder($service){
        $order_info = Session::get('order_info');
        if(!is_object($order_info) || ($order_info->service_id ?? null) != $service->id){
            return null;
        }

        return $order_info;
    }

    /**
     * What the customer must pay for the booking in the session, after any valid coupon.
     */
    private function payableTotal($service, $order_info){
        return BookingPricing::frozenTotals($service, $order_info)->total;
    }

    private function decodeList($json){
        $items = $json ? json_decode($json) : [];
        return is_array($items) ? $items : [];
    }

    private function missingBooking($service){
        $notification = trans('user_validation.Please select a schedule');
        $notification = array('messege'=>$notification,'alert-type'=>'error');
        return redirect()->route('ready-to-booking', $service->slug)->with($notification);
    }


    public function check_schedule_during_payment($slug){
        $service = Service::where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();
        $order_info = $service ? $this->sessionOrder($service) : null;

        if(!$order_info){
            return response()->json(['is_available' => false, 'message' => trans('user_validation.Please select a schedule')]);
        }

        $exist = $this->checkAvaibalityBeforPayment($service, $order_info->date, $order_info->schedule_time_slot);

        if($exist > 0){
            $notification = trans('user_validation.This schedule already booked. please choose another schedule');
            return response()->json(['is_available' => false, 'message' => $notification]);
        }else{
            return response()->json(['is_available' => true]);
        }

    }
    public function checkAvaibalityBeforPayment($service, $date, $schedule_id){
        $exist = BookingPricing::slotTaken($service->provider_id, $schedule_id, $date);
        return $exist;
    }


    public function bankPayment(Request $request, $slug){

        if(env('APP_MODE') == 'DEMO'){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $rules = [
            'tnx_info'=>'required|max:1000',
        ];
        $customMessages = [
            'tnx_info.required' => trans('user_validation.Transaction is required'),
        ];
        $this->validate($request, $rules,$customMessages);

        $service = Service::where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();
        if(!$service){
            abort(404);
        }

        $user = Auth::guard('web')->user();
        $order_info = $this->sessionOrder($service);
        if(!$order_info){
            return $this->missingBooking($service);
        }
        $provider_id = $service->provider_id;
        $client_id = $user->id;

        $exist = $this->checkAvaibalityBeforPayment($service, $order_info->date, $order_info->schedule_time_slot);

        if($exist > 0){
            $notification = trans('user_validation.This schedule already booked. please choose another schedule');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('ready-to-booking', $service->slug)->with($notification);
        }

        $order = $this->createOrder($user, $service, $order_info, $provider_id, $client_id, 'Bank Payment', 'pending', $request->tnx_info);

        $provider = $service->provider;
        $this->sendMailToClient($user, $order);
        $this->sendMailToProvider($provider, $order);

        Session::forget('order_info');

        $notification = trans('user_validation.Your order has been placed. please wait for admin payment approval');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->route('dashboard')->with($notification);
    }

    public function payWithStripe(Request $request, $slug){

        if(env('APP_MODE') == 'DEMO'){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $service = Service::where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();
        if(!$service){
            abort(404);
        }

        $user = Auth::guard('web')->user();
        $order_info = $this->sessionOrder($service);
        if(!$order_info){
            return $this->missingBooking($service);
        }
        $provider_id = $service->provider_id;
        $client_id = $user->id;

        $exist = $this->checkAvaibalityBeforPayment($service, $order_info->date, $order_info->schedule_time_slot);

        if($exist > 0){
            $notification = trans('user_validation.This schedule already booked. please choose another schedule');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('ready-to-booking', $service->slug)->with($notification);
        }

        $stripe = StripePayment::first();
        $payableAmount = round($this->payableTotal($service, $order_info) * $stripe->currency_rate,2);
        Stripe\Stripe::setApiKey($stripe->stripe_secret);

        $result = Stripe\Charge::create ([
                "amount" => (int) round($payableAmount * 100),
                "currency" => $stripe->currency_code,
                "source" => $request->stripeToken,
                "description" => env('APP_NAME')
            ]);

        $order = $this->createOrder($user, $service, $order_info, $provider_id, $client_id, 'Stripe', 'success', $result->balance_transaction);

        $provider = $service->provider;
        $this->sendMailToClient($user, $order);
        $this->sendMailToProvider($provider, $order);

        Session::forget('order_info');

        $notification = trans('user_validation.Your order has been placed. Thanks for your new order');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->route('dashboard')->with($notification);

    }

    public function payWithRazorpay(Request $request, $slug){
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
            return $this->missingBooking($service);
        }

        $failed = array('messege'=>trans('user_validation.Payment Faild'),'alert-type'=>'error');
        if(empty($request->razorpay_payment_id)){
            return redirect()->back()->with($failed);
        }

        $razorpay = RazorpayPayment::first();
        $api = new Api($razorpay->key,$razorpay->secret_key);

        try {
            $payment = $api->payment->fetch($request->razorpay_payment_id);

            // The payment id comes from the browser, so check it is for this booking's amount.
            $expected = (int) round(round($this->payableTotal($service, $order_info) * $razorpay->currency_rate, 2) * 100);
            if(!in_array($payment['status'], ['authorized', 'captured'])
                || !BookingPricing::amountCovers($payment['amount'], $expected, 1)
                || strtoupper($payment['currency']) != strtoupper($razorpay->currency_code)
                || BookingPricing::transactionUsed('Razorpay', $payment['id'])){
                return redirect()->back()->with($failed);
            }

            if($payment['status'] == 'authorized'){
                $payment = $payment->capture(array('amount'=>$payment['amount']));
            }
            if($payment['status'] != 'captured'){
                return redirect()->back()->with($failed);
            }
            $payId = $payment['id'];

            $user = Auth::guard('web')->user();
            $provider_id = $service->provider_id;
            $client_id = $user->id;

            $exist = $this->checkAvaibalityBeforPayment($service, $order_info->date, $order_info->schedule_time_slot);

            if($exist > 0){
                $notification = trans('user_validation.This schedule already booked. please choose another schedule');
                $notification = array('messege'=>$notification,'alert-type'=>'error');
                return redirect()->route('ready-to-booking', $service->slug)->with($notification);
            }

            $order = $this->createOrder($user, $service, $order_info, $provider_id, $client_id, 'Razorpay', 'success', $payId);

            $provider = $service->provider;
            $this->sendMailToClient($user, $order);
            $this->sendMailToProvider($provider, $order);

            Session::forget('order_info');

            $notification = trans('user_validation.Your order has been placed. Thanks for your new order');
            $notification = array('messege'=>$notification,'alert-type'=>'success');
            return redirect()->route('dashboard')->with($notification);

        }catch (Exception $e) {
            return redirect()->back()->with($failed);
        }
    }

    public function payWithFlutterwave(Request $request, $slug){

        if(env('APP_MODE') == 'DEMO'){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $failed = ['status' => 'faild' , 'message' => trans('user_validation.Payment Faild')];

        $service = Service::where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();
        $order_info = $service ? $this->sessionOrder($service) : null;
        if(!$order_info){
            return response()->json($failed);
        }

        $flutterwave = Flutterwave::first();
        $tnx_id = $request->tnx_id;
        $response = $this->verifyFlutterwave($flutterwave, $tnx_id);

        // The transaction id comes from the browser, so check it is for this booking's amount.
        $expected = round($this->payableTotal($service, $order_info) * $flutterwave->currency_rate, 2);
        $paid = ($response->status ?? null) == 'success'
            && ($response->data->status ?? null) == 'successful'
            && BookingPricing::amountCovers($response->data->amount ?? null, $expected)
            && strtoupper($response->data->currency ?? '') == strtoupper($flutterwave->currency_code)
            && !BookingPricing::transactionUsed('Flutterwave', $tnx_id);

        if($paid){
            $user = Auth::guard('web')->user();
            $provider_id = $service->provider_id;
            $client_id = $user->id;

            $order = $this->createOrder($user, $service, $order_info, $provider_id, $client_id, 'Flutterwave', 'success', $tnx_id);

            $provider = $service->provider;
            $this->sendMailToClient($user, $order);
            $this->sendMailToProvider($provider, $order);

            Session::forget('order_info');

            $notification = trans('user_validation.Your order has been placed. Thanks for your new order');
            return response()->json(['status' => 'success' , 'message' => $notification]);
        }else{
            return response()->json($failed);
        }
    }

    public function payWithMollie(Request $request, $slug){

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
            return $this->missingBooking($service);
        }

        $exist = $this->checkAvaibalityBeforPayment($service, $order_info->date, $order_info->schedule_time_slot);

        if($exist > 0){
            $notification = trans('user_validation.This schedule already booked. please choose another schedule');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('ready-to-booking', $service->slug)->with($notification);
        }

        $mollie = PaystackAndMollie::first();
        $price = $this->payableTotal($service, $order_info) * $mollie->mollie_currency_rate;
        $price = round($price,2);
        $price = sprintf('%0.2f', $price);

        $mollie_api_key = $mollie->mollie_key;
        $currency = strtoupper($mollie->mollie_currency_code);
        Mollie::api()->setApiKey($mollie_api_key);
        $payment = Mollie::api()->payments()->create([
            'amount' => [
                'currency' => $currency,
                'value' => ''.$price.'',
            ],
            'description' => env('APP_NAME'),
            'redirectUrl' => route('mollie-payment-success'),
        ]);

        $payment = Mollie::api()->payments()->get($payment->id);
        session()->put('payment_id',$payment->id);
        session()->put('service',$service);
        return redirect($payment->getCheckoutUrl(), 303);
    }

    public function molliePaymentSuccess(Request $request){

        $session_service = Session::get('service');
        Session::put('return_from_mollie','payment_faild');

        $service = $session_service ? Service::where(['slug' => $session_service->slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first() : null;
        $order_info = $service ? $this->sessionOrder($service) : null;
        if(!$order_info){
            $notification = trans('user_validation.Payment Faild');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('home')->with($notification);
        }

        $exist = $this->checkAvaibalityBeforPayment($service, $order_info->date, $order_info->schedule_time_slot);

        if($exist > 0){
            $notification = trans('user_validation.This schedule already booked. please choose another schedule');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('ready-to-booking', $service->slug)->with($notification);
        }

        $mollie = PaystackAndMollie::first();
        $mollie_api_key = $mollie->mollie_key;
        Mollie::api()->setApiKey($mollie_api_key);
        $payment = Mollie::api()->payments->get(session()->get('payment_id'));

        $expected = round($this->payableTotal($service, $order_info) * $mollie->mollie_currency_rate, 2);
        $paid = $payment->isPaid()
            && BookingPricing::amountCovers($payment->amount->value, $expected)
            && !BookingPricing::transactionUsed('Mollie', $payment->id);

        if ($paid){
            $user = Auth::guard('web')->user();
            $provider_id = $service->provider_id;
            $client_id = $user->id;

            $order = $this->createOrder($user, $service, $order_info, $provider_id, $client_id, 'Mollie', 'success', $payment->id);

            $provider = $service->provider;
            $this->sendMailToClient($user, $order);
            $this->sendMailToProvider($provider, $order);

            Session::forget('order_info');
            Session::forget('return_from_mollie');

            $notification = trans('user_validation.Your order has been placed. Thanks for your new order');
            $notification = array('messege'=>$notification,'alert-type'=>'success');
            return redirect()->route('dashboard')->with($notification);
        }else{
            $notification = trans('user_validation.Payment Faild');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('payment', $service->slug)->with($notification);
        }
    }

    public function payWithPayStack(Request $request, $slug){

        if(env('APP_MODE') == 'DEMO'){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $failed = ['status' => 'faild' , 'message' => trans('user_validation.Payment Faild')];

        $service = Service::where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();
        $order_info = $service ? $this->sessionOrder($service) : null;
        if(!$order_info){
            return response()->json($failed);
        }

        $paystack = PaystackAndMollie::first();
        $final_data = $this->verifyPaystack($paystack, $request->reference);

        // The reference comes from the browser, so check the transaction itself: that it
        // succeeded, is for this booking's amount (in kobo/cents) and was not used before.
        $expected = (int) round($this->payableTotal($service, $order_info) * $paystack->paystack_currency_rate * 100);
        $transaction = $final_data->data->id ?? null;
        $paid = ($final_data->status ?? false) == true
            && ($final_data->data->status ?? null) == 'success'
            && BookingPricing::amountCovers($final_data->data->amount ?? null, $expected, 1)
            && strtoupper($final_data->data->currency ?? '') == strtoupper($paystack->paystack_currency_code)
            && !BookingPricing::transactionUsed('Paystack', $transaction);

        if($paid) {
            $user = Auth::guard('web')->user();
            $provider_id = $service->provider_id;
            $client_id = $user->id;

            $order = $this->createOrder($user, $service, $order_info, $provider_id, $client_id, 'Paystack', 'success', $transaction);

            $provider = $service->provider;
            $this->sendMailToClient($user, $order);
            $this->sendMailToProvider($provider, $order);

            Session::forget('order_info');

            $notification = trans('user_validation.Your order has been placed. Thanks for your new order');
            return response()->json(['status' => 'success' , 'message' => $notification]);
        }else{
            return response()->json($failed);
        }
    }

    public function payWithInstamojo(Request $request, $slug){

        if(env('APP_MODE') == 'DEMO'){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $service = Service::where(['slug' => $slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first();
        if(!$service){
            abort(404);
        }

        $user = Auth::guard('web')->user();
        $order_info = $this->sessionOrder($service);
        if(!$order_info){
            return $this->missingBooking($service);
        }

        $exist = $this->checkAvaibalityBeforPayment($service, $order_info->date, $order_info->schedule_time_slot);

        if($exist > 0){
            $notification = trans('user_validation.This schedule already booked. please choose another schedule');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('ready-to-booking', $service->slug)->with($notification);
        }

        $instamojoPayment = InstamojoPayment::first();
        $price = $this->payableTotal($service, $order_info) * $instamojoPayment->currency_rate;
        $price = round($price,2);

        $environment = $instamojoPayment->account_mode;
        $api_key = $instamojoPayment->api_key;
        $auth_token = $instamojoPayment->auth_token;

        if($environment == 'Sandbox') {
            $url = 'https://test.instamojo.com/api/1.1/';
        } else {
            $url = 'https://www.instamojo.com/api/1.1/';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url.'payment-requests/');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array("X-Api-Key:$api_key",
                "X-Auth-Token:$auth_token"));
        $payload = Array(
            'purpose' => env("APP_NAME"),
            'amount' => $price,
            'phone' => $this->instamojoPhone($order_info->customer->phone),
            'buyer_name' => $user->name,
            'redirect_url' => route('response-instamojo'),
            'send_email' => true,
            'send_sms' => true,
            'email' => $user->email,
            'allow_repeated_payments' => false
        );
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response);
        if(empty($response->payment_request->longurl)){
            $notification = array('messege'=>trans('user_validation.Payment Faild'),'alert-type'=>'error');
            return redirect()->route('payment', $service->slug)->with($notification);
        }
        Session::put('service', $service);
        return redirect($response->payment_request->longurl);
    }

    /**
     * Instamojo rejects badly formatted numbers; send digits only, or nothing.
     */
    private function instamojoPhone($phone){
        $digits = preg_replace('/\D/', '', html_decode((string) $phone));
        return (strlen($digits) >= 10 && strlen($digits) <= 15) ? $digits : '';
    }

    public function instamojoResponse(Request $request){

        Session::put('return_from_mollie','payment_faild');
        $session_service = Session::get('service');

        $service = $session_service ? Service::where(['slug' => $session_service->slug, 'approve_by_admin' => 1, 'status' => 1, 'is_banned' => 0])->first() : null;
        $order_info = $service ? $this->sessionOrder($service) : null;

        $failed = array('messege'=>trans('user_validation.Payment Faild'),'alert-type'=>'error');
        if(!$order_info){
            return redirect()->route('home')->with($failed);
        }

        $exist = $this->checkAvaibalityBeforPayment($service, $order_info->date, $order_info->schedule_time_slot);

        if($exist > 0){
            $notification = trans('user_validation.This schedule already booked. please choose another schedule');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('ready-to-booking', $service->slug)->with($notification);
        }

        $instamojoPayment = InstamojoPayment::first();
        $payment_id = $request->get('payment_id');
        $data = $this->fetchInstamojoPayment($instamojoPayment, $payment_id);

        // The payment id comes from the redirect URL, so check the payment itself.
        $expected = round($this->payableTotal($service, $order_info) * $instamojoPayment->currency_rate, 2);
        $paid = $data
            && ($data->success ?? false) == true
            && ($data->payment->status ?? null) == 'Credit'
            && BookingPricing::amountCovers($data->payment->amount ?? null, $expected)
            && !BookingPricing::transactionUsed('Instamojo', $payment_id);

        if(!$paid){
            return redirect()->route('payment', $service->slug)->with($failed);
        }

        $user = Auth::guard('web')->user();
        $provider_id = $service->provider_id;
        $client_id = $user->id;

        $order = $this->createOrder($user, $service, $order_info, $provider_id, $client_id, 'Instamojo', 'success', $payment_id);

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

    private function verifyFlutterwave($flutterwave, $tnx_id){
        if(!$tnx_id || !ctype_digit((string) $tnx_id)){
            return null;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/$tnx_id/verify",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer ".$flutterwave->secret_key
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        return $response ? json_decode($response) : null;
    }

    private function verifyPaystack($paystack, $reference){
        if(!$reference){
            return null;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/".rawurlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer ".$paystack->paystack_secret_key,
                "Cache-Control: no-cache",
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        return $response ? json_decode($response) : null;
    }

    private function fetchInstamojoPayment($instamojoPayment, $payment_id){
        if(!$payment_id){
            return null;
        }

        $url = $instamojoPayment->account_mode == 'Sandbox' ? 'https://test.instamojo.com/api/1.1/' : 'https://www.instamojo.com/api/1.1/';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url.'payments/'.rawurlencode($payment_id).'/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array("X-Api-Key:".$instamojoPayment->api_key,
                "X-Auth-Token:".$instamojoPayment->auth_token));
        $response = curl_exec($ch);
        curl_close($ch);

        return $response ? json_decode($response) : null;
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
        $coupon = !empty($totals->coupon_code) ? Coupon::where('coupon_code', $totals->coupon_code)->first() : null;
        $coupon_discount = $totals->discount;

        if($coupon){
            $history = new CouponHistory();
            $history->user_id = $client_id;
            $history->provider_id = $coupon->provider_id;
            $history->coupon_code = $coupon->coupon_code;
            $history->coupon_id = $coupon->id;
            $history->discount_amount = $coupon_discount;
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
        $order->coupon_discount  = $coupon_discount;
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
