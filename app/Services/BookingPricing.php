<?php

namespace App\Services;

use App\Models\AdditionalService;
use App\Models\AppointmentSchedule;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

/**
 * Server-side source of truth for what a booking costs and whether it can be booked.
 *
 * Prices, totals and discounts sent by the browser or the mobile app are never trusted:
 * add-on prices come from additional_services, coupons are re-checked against the
 * service's provider, and gateway amounts are compared with what we expect to be paid.
 */
class BookingPricing
{
    const MAX_EXTRA_QUANTITY = 100;

    /**
     * Price the selected add-ons from the database.
     *
     * Only add-ons that belong to $service are accepted, and every quantity must be a
     * whole number between 1 and MAX_EXTRA_QUANTITY.
     *
     * @return object {ids, quantities, names, prices (line totals), extra_price}
     */
    public static function extras(Service $service, $ids, $quantities): object
    {
        $quantities = (array) $quantities;

        $extras = (object) ['ids' => [], 'quantities' => [], 'names' => [], 'prices' => [], 'extra_price' => 0];

        foreach ((array) $ids as $index => $id) {
            if ($id === null || $id === '') {
                continue;
            }

            $quantity = filter_var($quantities[$index] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => self::MAX_EXTRA_QUANTITY],
            ]);
            if ($quantity === false) {
                throw ValidationException::withMessages([
                    'quantities' => __('Each extra service quantity must be a whole number between 1 and :max.', ['max' => self::MAX_EXTRA_QUANTITY]),
                ]);
            }

            $addition = AdditionalService::where('service_id', $service->id)->find($id);
            if (!$addition) {
                throw ValidationException::withMessages(['ids' => __('One of the selected extra services is not available for this service.')]);
            }

            $extras->ids[] = $addition->id;
            $extras->quantities[] = $quantity;
            $extras->names[] = $addition->service_name;
            $extras->prices[] = round($quantity * $addition->price, 2);
        }

        $extras->extra_price = round(array_sum($extras->prices), 2);

        return $extras;
    }

    /**
     * The booking date in the one format orders are stored and compared in (d-m-Y),
     * or null. Only d-m-Y (website) and Y-m-d (app) are accepted.
     */
    public static function bookingDate($date): ?string
    {
        foreach (['d-m-Y', 'Y-m-d'] as $format) {
            $parsed = \DateTime::createFromFormat('!'.$format, trim((string) $date));
            if ($parsed && $parsed->format($format) === trim((string) $date)) {
                return $parsed->format('d-m-Y');
            }
        }

        return null;
    }

    /**
     * Check that $scheduleId is an active, well-formed slot of the service's provider on
     * the weekday of $date, and that $date is today or later.
     *
     * @return string the normalised booking date (d-m-Y)
     */
    public static function slot(Service $service, $date, $scheduleId): string
    {
        $bookingDate = self::bookingDate($date);
        $timestamp = $bookingDate ? strtotime($bookingDate) : false;
        if ($timestamp === false || $timestamp < strtotime('today')) {
            throw ValidationException::withMessages(['date' => __('Please choose a booking date from today onwards.')]);
        }

        $schedule = AppointmentSchedule::where(['id' => $scheduleId, 'user_id' => $service->provider_id, 'status' => 1])->first();
        if (!$schedule
            || strcasecmp($schedule->day, date('l', $timestamp)) !== 0
            || strtotime($schedule->end_time) <= strtotime($schedule->start_time)) {
            throw ValidationException::withMessages(['schedule_time_slot' => __('This time slot is not available on the selected date.')]);
        }

        return $bookingDate;
    }

    /**
     * Whether a provider's slot is already booked on a date. Declined bookings free the slot.
     */
    public static function slotTaken($providerId, $scheduleId, $date): bool
    {
        return Order::where(['provider_id' => $providerId, 'appointment_schedule_id' => $scheduleId, 'booking_date' => self::bookingDate($date) ?? $date])
            ->whereNotIn('order_status', ['order_decliened_by_provider', 'order_decliened_by_client'])
            ->exists();
    }

    /**
     * The coupon in the session, if it is still valid for this service's provider.
     * An invalid coupon is removed from the session.
     */
    public static function sessionCoupon(Service $service): ?Coupon
    {
        $code = Session::get('coupon_code');
        if (!$code) {
            return null;
        }

        $coupon = self::validCoupon($service, $code);
        if (!$coupon) {
            Session::forget(['coupon_code', 'offer_percentage']);
        }

        return $coupon;
    }

    /**
     * An active, unexpired coupon issued by the platform (provider_id 0) or by the
     * service's own provider.
     */
    public static function validCoupon(Service $service, ?string $code): ?Coupon
    {
        if (!$code) {
            return null;
        }

        $coupon = Coupon::where(['coupon_code' => $code, 'status' => 1])->first();
        if (!$coupon || $coupon->expired_date < date('Y-m-d')) {
            return null;
        }
        if ($coupon->provider_id != 0 && $coupon->provider_id != $service->provider_id) {
            return null;
        }

        return $coupon;
    }

    /**
     * Totals for a booking: package + add-ons, minus a coupon capped at 100%.
     *
     * @return object {package_price, extra_price, subtotal, discount, total}
     */
    public static function totals(Service $service, float $extraPrice, ?Coupon $coupon = null): object
    {
        $subtotal = round($service->price + $extraPrice, 2);
        $percentage = $coupon ? max(0, min(100, (float) $coupon->offer_percentage)) : 0;
        $discount = round($subtotal * $percentage / 100, 2);

        return (object) [
            'package_price' => (float) $service->price,
            'extra_price' => round($extraPrice, 2),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => round($subtotal - $discount, 2),
        ];
    }

    /**
     * Totals frozen into the session booking when the payment page was shown, so a price
     * or coupon change while the customer is at the gateway cannot fail a payment that
     * was already taken. Falls back to computing them for older sessions.
     */
    public static function frozenTotals(Service $service, $orderInfo): object
    {
        if (isset($orderInfo->totals)) {
            return (object) $orderInfo->totals;
        }

        return self::totals($service, (float) $orderInfo->extra_price, self::sessionCoupon($service));
    }

    /**
     * Whether a gateway transaction id has already been used to pay for an order.
     */
    public static function transactionUsed(string $paymentMethod, $transactionId): bool
    {
        if ($transactionId === null || $transactionId === '') {
            return true;
        }

        return Order::where(['payment_method' => $paymentMethod, 'transection_id' => $transactionId])->exists();
    }

    /**
     * Whether a gateway-reported amount covers what we expect, allowing for rounding.
     * Both amounts must be in the same unit (e.g. both in pence, or both in pounds).
     */
    public static function amountCovers($paid, $expected, $tolerance = 0.01): bool
    {
        return is_numeric($paid) && (float) $paid + $tolerance >= (float) $expected;
    }
}
