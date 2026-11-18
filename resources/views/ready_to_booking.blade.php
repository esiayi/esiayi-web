@extends($active_theme)
@section('title')
    <title>{{ $service->name }}</title>
    <title>{{ $service->seo_title }}</title>
@endsection
@section('meta')
    <meta name="description" content="{{ $service->seo_description }}">
@endsection

@section('frontend-content')
    <!--=========================
        BREADCRUMB START
    ==========================-->
    <div class="wsus__breadcrumb" style="background: url({{ asset($breadcrumb->image) }});">
        <div class="wsus__breadcrumb_overlay pt_90 xs_pt_60 pb_95 xs_pb_65">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <nav aria-label="breadcrumb">
                            <h1>{{__('user.Ready to Booking')}}</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('home') }}">{{__('user.Home')}}</a></li>
                                <li class="breadcrumb-item active" aria-current="page">{{__('user.Ready to Booking')}}</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--=========================
        BREADCRUMB END
    ==========================-->


        <!--=========================
        BOOKING SERVICE START
    ==========================-->
    <section class="wsus__booking_service mt_100 xs_mt_70 mb_100 xs_mb_70">
        <div class="container">
            <div class="row">
                <div class="col-xl-8 col-lg-8">
                    <div class="wsus__booking_area">
                        <ul class="booking_bar d-flex flex-wrap">
                            <li class="active">1 <span>{{__('user.Service')}}</span></li>
                            <li>2 <span>{{__('user.Information')}}</span></li>
                            <li>3 <span>{{__('user.Confirmation')}}</span></li>
                        </ul>
                        <div class="wsus__booking_img">
                            <img src="{{ asset($service->image) }}" alt="booking images" class="img-fluid w-100">
                        </div>
                        <div class="wsus__booking_text">
                            <h2>{{ $service->name }}</h2>

                            <div class="row">
                                @if (count($what_you_will_get) > 0)
                                    <div class="col-xl-6">
                                        <div class="wsus__booking_list_text">
                                            <h3>{{__('user.What you will get')}}:</h3>
                                            <ul class="list">
                                                @foreach ($what_you_will_get as $get_item)
                                                <li>{{ $get_item }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif

                                @if (count($benifits) > 0)
                                    <div class="col-xl-6">
                                        <div class="wsus__booking_list_text">
                                            <h3>{{__('user.Benifits of the Package')}}:</h3>
                                            <ul class="list">
                                                @foreach ($benifits as $benifit)
                                                <li>{{ $benifit }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if ($additional_services->count() > 0)
                                <div class="wsus__service_cart">
                                    <h4>{{__('user.Upgrade your order with extras')}}</h4>
                                    <div class="wsus__service_cart_bg">
                                        <div class="table-responsive">
                                            <table class="table">
                                                <tbody>
                                                    <tr>
                                                        <th class="images">{{__('user.images')}}</th>
                                                        <th class="name">{{__('user.Service Name')}}</th>
                                                        <th class="price">{{__('user.Unit Price')}}</th>
                                                        <th class="qty">{{__('user.Quantity')}}</th>
                                                    </tr>

                                                    @foreach ($additional_services as $index => $additional_service)
                                                    <tr>
                                                        <td class="images">
                                                            <img src="{{ asset($additional_service->image) }}" alt="cart images"
                                                                class="img-fluid w-100">
                                                        </td>
                                                        <td class="name">
                                                            <div class="form-check">
                                                                <input onclick="checkExtraService({{ $additional_service->id }}, {{ (float) $additional_service->price }}, this.dataset.name)" data-name="{{ $additional_service->service_name }}" class="form-check-input" type="checkbox" value=""
                                                                    id="flexCheckDefault-{{ $index }}">
                                                                <label class="form-check-label" for="flexCheckDefault-{{ $index }}">
                                                                    {{ $additional_service->service_name }}
                                                                </label>
                                                            </div>
                                                        </td>
                                                        <td class="price">
                                                            <p>{{ $currency_icon->icon }}{{ $additional_service->price }}</p>
                                                        </td>
                                                        <td class="qty">
                                                            <input min="1" max="100" step="1" onchange="qtyUpdate({{ $additional_service->id }}, {{ (float) $additional_service->price }})" id="service_qty_{{ $additional_service->id }}" type="number" value="{{ max(1, (int) $additional_service->qty) }}">
                                                        </td>

                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <ul class="wsus__booking_button_area d-flex">
                        <li><a href="{{ route('service', $service->slug) }}" class="common_btn">{{__('user.Previous')}}</a></li>

                        <li><a href="javascript:;" id="readyToBookingBtn" class="common_btn">{{__('user.Next')}}</a></li>
                    </ul>
                </div>
                <div class="col-xl-4 col-lg-4">
                    <div class="wsus__sidebar" id="sticky_sidebar">

                        <div class="wsus__booking_calendar">
                            @if (count($available_days) > 0)
                                <div id="service_available_dates"></div>
                            @else
                                <p class="p-4 m-0">{{ __('This provider has not set their availability yet. Please choose another service or contact us.') }}</p>
                            @endif
                        </div>

                        <div class="wsus__booking_pic_up mt_25">
                            <h3>{{__('user.Select Schedule')}}</h3>
                            <select id="schedule_box">
                                <option value="">{{__('user.Select')}}</option>
                            </select>
                        </div>

                        <div class="wsus__booking_summery">
                            <h3>{{__('user.Booking Summery')}}</h3>
                            <ul>
                                @foreach ($package_features as $package_feature)
                                    <li>{{ $package_feature }}</li>
                                @endforeach
                            </ul>
                            <div class="wsus__booking_cost">
                                <p class="package_fee">{{__('user.Package Fee')}} <span>{{ $currency_icon->icon }}{{ $service->price }}</span></p>
                                <ul class="extra_service_area">

                                </ul>
                                @php
                                    $extra_service = 0.00;
                                @endphp
                                <h4>{{__('user.Extra Service')}} <span>{{ $currency_icon->icon }}<span id="extra_service_price">{{ round($extra_service, 2) }}</span> </span></h4>

                                <h5>{{__('user.Total')}} <span>{{ $currency_icon->icon }}<span id="total_price">{{ round($service->price + $extra_service, 2) }}</span></span></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Prices are worked out on the server; only the chosen add-ons, quantities, date and slot are sent. --}}
    <form action="{{ route('booking-information', $service->slug) }}" id="submitReadyToBooking">
        <input type="hidden" id="input_sub_total" value="{{ round($service->price, 2) }}">
        <input type="hidden" id="input_date" name="date" value="">
        <input type="hidden" id="schedule_time_slot" name="schedule_time_slot" value="">

        <div id="extra_input">
        </div>
    </form>

    <!--=========================
        BOOKING SERVICE END
    ==========================-->

<script>
    let currency_icon = "{{ $currency_icon->icon }}"
    let extraService = [];

    (function($) {
    "use strict";
        $(document).ready(function () {
            $("#readyToBookingBtn").on("click", function(){
                if(!$("#input_date").val()){
                    toastr.error("{{__('user.Please select a date')}}")
                    return;
                }

                if(!$("#schedule_time_slot").val()){
                    toastr.error("{{__('user.Please select a schedule')}}")
                    return;
                }

                $("#submitReadyToBooking").submit();
            })

            //Date and time. Only weekdays the provider works are selectable.
            const availableDays = @json($available_days);
            const dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

            if ($("#service_available_dates").length) {
                $("#service_available_dates").flatpickr({
                    minDate: "today",
                    inline: true,
                    altInput: true,
                    altFormat: "F j, Y",
                    dateFormat: "d-m-Y",
                    enable: [function(date){ return availableDays.indexOf(dayNames[date.getDay()]) !== -1; }],
                    onChange: function(selectedDates, date_string){ loadSchedules(date_string); }
                });
            }

            function loadSchedules(date_string){
                $("#input_date").val(date_string)
                $("#schedule_time_slot").val('');
                let provider_id = "{{ $service->provider_id }}";
                $.ajax({
                    type: 'get',
                    data: {date : date_string, provider_id : provider_id},
                    url: "{{ route('get-available-schedule') }}",
                    success: function (response) {
                        if(response.is_available){
                            $("#schedule_box").html(response.available_schedules);
                        }else{
                            let html = `<option value="">{{__('user.Select')}}</option>`;
                            $("#schedule_box").html(html);

                            toastr.error("{{__('user.Schedule Not Found')}}")
                        }
                    },
                    error: function(response) {
                        let html = `<option value="">{{__('user.Select')}}</option>`;
                        $("#schedule_box").html(html);

                        toastr.error("{{__('user.Something went wrong')}}")
                    }
                });
            }

            $("#schedule_box").on("change", function(){
                $("#schedule_time_slot").val($(this).val());
            })

        });
    })(jQuery);

    function toISODate(d) {
             const z = n => ('0' + n).slice(-2);
             return d.getFullYear() + '-' + z(d.getMonth()+1) + '-' +
             z(d.getDate());
        }

    function readQty(id){
        let qty = parseInt($("#service_qty_"+id).val(), 10);
        if (!qty || qty < 1) { qty = 1; }
        if (qty > 100) { qty = 100; }
        $("#service_qty_"+id).val(qty);
        return qty;
    }

    function checkExtraService(id, price, name){

        if(!extraService.some(service => service.id == id)){
            let qty = readQty(id);
            extraService.push({
                id : id,
                name : name,
                unit_price : price,
                price : price * qty,
                qty : qty
            });
        }else{
            extraService = extraService.filter(service => service.id !== id)
        }
        loadExtraService();
    }

    function loadExtraService(){
        let extra_price = 0;
        let $list = $(".extra_service_area").empty();
        let $inputs = $("#extra_input").empty();

        extraService.forEach(service => {
            extra_price += service.price;

            // Built with text() so add-on names are never interpreted as HTML.
            let $p = $("<p>").text(service.name + " ").append($("<b>").text("x " + service.qty));
            $list.append($("<li>").append($p).append($("<span>").text(currency_icon + service.price)));

            $inputs.append($("<input>", { type: "hidden", name: "ids[]", value: service.id }));
            $inputs.append($("<input>", { type: "hidden", name: "quantities[]", value: service.qty }));
        });

        $("#extra_service_price").text(extra_price);

        let sub_total = $("#input_sub_total").val();
        $("#total_price").text(sub_total*1 + extra_price*1);
    }

    function qtyUpdate(id, unit_price){
        let qty = readQty(id);
        if(extraService.some(service => service.id == id)){
            extraService = extraService.map(service => service.id == id ? { ...service, qty: qty, price: unit_price * qty } : service);
            loadExtraService();
        }
    }
</script>

@endsection
