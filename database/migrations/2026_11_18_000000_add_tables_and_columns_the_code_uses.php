<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The 2021 template migrations stopped matching the code long ago; the real schema
 * only existed in the production database, so a fresh install could not boot.
 *
 * This migration adds the tables and columns the code uses (reconstructed from the
 * code, so types are best guesses), guarded so that it only ADDS what is missing and
 * never changes an existing column. On a database that already has them, such as
 * production, it does nothing. It also relaxes NOT NULL on template columns the
 * code never fills, but only for empty tables, i.e. on a fresh install.
 *
 * Replace with a real schema dump once one is available:
 *   php artisan schema:dump   (run against production; writes database/schema/mysql-schema.sql)
 */
return new class extends Migration
{
    /** Tables the code uses that no earlier migration creates. */
    private const NEW_TABLES = ['counters', 'currencies', 'currency_countries', 'partners', 'provider_withdraws', 'reviews', 'wishlists'];

    /** Columns the code uses that the earlier migrations do not create. */
    private const COLUMNS = [
        'about_us' => [
            'about_us' => 'text',
            'about_us_title' => 'text',
            'background_image' => 'string',
            'description_one' => 'text',
            'description_three' => 'text',
            'description_two' => 'text',
            'foreground_image' => 'string',
            'header' => 'string',
            'header_description' => 'text',
            'small_image_one' => 'string',
            'small_image_three' => 'string',
            'small_image_two' => 'string',
            'title_one' => 'string',
            'title_three' => 'string',
            'title_two' => 'string',
            'total_rating' => 'decimal',
            'why_choose_background' => 'string',
            'why_choose_desciption' => 'string',
            'why_choose_foreground' => 'string',
            'why_choose_us_title' => 'string',
        ],
        'admins' => [
            'admin_type' => 'string',
            'forget_password_token' => 'string',
            'image' => 'string',
            'status' => 'integer',
        ],
        'appointment_schedules' => [
            'status' => 'integer',
        ],
        'bank_payments' => [
            'image' => 'string',
        ],
        'blog_comments' => [
            'status' => 'integer',
        ],
        'blogs' => [
            'banner_image' => 'string',
            'total_comment' => 'decimal',
        ],
        'categories' => [
            'icon' => 'string',
            'image' => 'string',
        ],
        'complete_requests' => [
            'order_status' => 'string',
        ],
        'contact_pages' => [
            'message' => 'text',
            'name' => 'string',
            'off_day' => 'string',
            'subject' => 'string',
            'support_time' => 'string',
            'supporter_image' => 'string',
        ],
        'counters' => [
            'icon' => 'string',
            'number' => 'integer',
            'status' => 'integer',
            'title' => 'string',
        ],
        'countries' => [
            'code' => 'string',
        ],
        'coupon_histories' => [
            'discount_amount' => 'decimal',
        ],
        'coupons' => [
            'status' => 'integer',
        ],
        'currencies' => [
            'code' => 'string',
            'name' => 'string',
        ],
        'currency_countries' => [
            'name' => 'string',
        ],
        'custom_pages' => [
            'slug' => 'string',
        ],
        'faqs' => [
            'answer' => 'text',
        ],
        'flutterwaves' => [
            'country_code' => 'string',
            'currency_code' => 'string',
            'currency_rate' => 'double',
        ],
        'footers' => [
            'about_us' => 'text',
            'copyright' => 'text',
            'payment_image' => 'string',
        ],
        'how_it_works' => [
            'background' => 'string',
            'foreground' => 'string',
        ],
        'instamojo_payments' => [
            'image' => 'string',
        ],
        'maintainance_texts' => [
            'status' => 'integer',
        ],
        'menu_visibilities' => [
            'custom_name' => 'string',
        ],
        'messages' => [
            'admin' => 'string',
            'admin_id' => 'unsignedBigInteger',
            'buyer_id' => 'unsignedBigInteger',
            'buyer_read_msg' => 'string',
            'documents' => 'text',
            'message_from' => 'text',
            'provider_id' => 'unsignedBigInteger',
            'provider_read_msg' => 'string',
            'send_by' => 'string',
            'service_id' => 'unsignedBigInteger',
            'ticket_id' => 'unsignedBigInteger',
            'unseen_admin' => 'string',
            'unseen_user' => 'string',
            'user_id' => 'unsignedBigInteger',
        ],
        'mobile_sliders' => [
            'title_one' => 'string',
            'title_two' => 'string',
        ],
        'orders' => [
            'additional_amount' => 'decimal',
            'additional_services' => 'text',
            'appointment_schedule_id' => 'unsignedBigInteger',
            'booking_date' => 'string',
            'client_address' => 'text',
            'client_id' => 'unsignedBigInteger',
            'complete_by_admin' => 'string',
            'coupon_discount' => 'decimal',
            'order_note' => 'text',
            'package_amount' => 'decimal',
            'package_features' => 'text',
            'provider_id' => 'unsignedBigInteger',
            'schedule_time_slot' => 'string',
            'service_id' => 'unsignedBigInteger',
            'total_amount' => 'decimal',
        ],
        'partners' => [
            'link' => 'string',
            'logo' => 'string',
            'status' => 'integer',
        ],
        'paypal_payments' => [
            'country_code' => 'string',
            'currency_code' => 'string',
            'currency_rate' => 'double',
            'image' => 'string',
        ],
        'paystack_and_mollies' => [
            'mollie_country_code' => 'string',
            'mollie_currency_code' => 'string',
            'mollie_currency_rate' => 'double',
            'mollie_image' => 'string',
            'paystack_country_code' => 'string',
            'paystack_currency_code' => 'string',
            'paystack_currency_rate' => 'double',
            'paystack_image' => 'string',
            'paystack_public_key' => 'string',
            'paystack_secret_key' => 'string',
        ],
        'provider_withdraws' => [
            'account_info' => 'text',
            'approved_date' => 'string',
            'method' => 'string',
            'status' => 'integer',
            'total_amount' => 'decimal',
            'user_id' => 'unsignedBigInteger',
            'withdraw_amount' => 'decimal',
            'withdraw_charge' => 'decimal',
        ],
        'razorpay_payments' => [
            'country_code' => 'string',
            'currency_code' => 'string',
        ],
        'refund_requests' => [
            'account_information' => 'text',
        ],
        'reviews' => [
            'product_id' => 'unsignedBigInteger',
            'product_vendor_id' => 'unsignedBigInteger',
            'provider_id' => 'unsignedBigInteger',
            'rating' => 'integer',
            'review' => 'text',
            'service_id' => 'unsignedBigInteger',
            'status' => 'integer',
            'user_id' => 'unsignedBigInteger',
        ],
        'schedules' => [
            'end_time' => 'string',
            'serial_of_day' => 'integer',
            'start_time' => 'string',
            'user_id' => 'unsignedBigInteger',
        ],
        'section_contents' => [
            'qty' => 'integer',
            'status' => 'integer',
        ],
        'section_controls' => [
            'description' => 'text',
            'title' => 'string',
        ],
        'services' => [
            'approve_by_admin' => 'integer',
        ],
        'settings' => [
            'app_description' => 'text',
            'app_full_title' => 'string',
            'app_image' => 'string',
            'app_short_title' => 'string',
            'app_store_link' => 'string',
            'blog_page_subscription_image' => 'string',
            'contact_email' => 'string',
            'copyright' => 'text',
            'counter_bg_image' => 'string',
            'currency_icon' => 'string',
            'currency_name' => 'string',
            'default_avatar' => 'string',
            'footer_logo' => 'string',
            'google_playstore_link' => 'string',
            'home2_app_image' => 'string',
            'home2_contact_available' => 'string',
            'home2_contact_background' => 'string',
            'home2_contact_call_as' => 'string',
            'home2_contact_foreground' => 'string',
            'home2_contact_form_description' => 'text',
            'home2_contact_form_title' => 'string',
            'home2_contact_phone' => 'string',
            'home2_join_as_provider' => 'string',
            'home2_subscription_bg' => 'string',
            'home3_app_image' => 'string',
            'home3_join_as_provider' => 'string',
            'home3_subscription_bg' => 'string',
            'how_it_work_background' => 'string',
            'how_it_work_description' => 'text',
            'how_it_work_foreground' => 'string',
            'how_it_work_items' => 'text',
            'how_it_work_title' => 'string',
            'image' => 'string',
            'join_as_a_provider_banner' => 'string',
            'join_as_a_provider_btn' => 'string',
            'join_as_a_provider_title' => 'string',
            'login_image' => 'string',
            'opening_time' => 'string',
            'selected_theme' => 'string',
            'show_provider_contact_info' => 'integer',
            'subscriber_description' => 'text',
            'subscriber_image' => 'string',
            'subscriber_title' => 'string',
            'subscription_bg' => 'string',
            'theme_one_color' => 'string',
            'theme_three_color' => 'string',
            'theme_two_color' => 'string',
            'topbar_email' => 'string',
            'topbar_phone' => 'string',
        ],
        'sliders' => [
            'header_one' => 'string',
            'header_two' => 'string',
            'home2_image' => 'string',
            'home3_image' => 'string',
            'popular_tag' => 'text',
            'title_one' => 'string',
            'title_two' => 'string',
            'total_service_sold' => 'decimal',
        ],
        'social_login_information' => [
            'facebook_redirect_url' => 'string',
            'gmail_redirect_url' => 'string',
        ],
        'stripe_payments' => [
            'country_code' => 'string',
            'currency_code' => 'string',
            'currency_rate' => 'double',
            'image' => 'string',
        ],
        'subscribers' => [
            'background_image' => 'string',
            'blog_page_subscription_image' => 'string',
            'description' => 'text',
            'foreground_image' => 'string',
            'home2_background_image' => 'string',
            'home3_background_image' => 'string',
            'image' => 'string',
            'title' => 'string',
        ],
        'ticket_messages' => [
            'unseen_admin' => 'string',
            'unseen_user' => 'string',
        ],
        'users' => [
            'address' => 'text',
            'banner_image' => 'string',
            'city_id' => 'unsignedBigInteger',
            'closed_at' => 'string',
            'country_id' => 'unsignedBigInteger',
            'description' => 'text',
            'designation' => 'string',
            'email_verified' => 'integer',
            'forget_password_otp' => 'string',
            'forget_password_token' => 'string',
            'greeting_msg' => 'text',
            'image' => 'string',
            'is_provider' => 'integer',
            'open_at' => 'string',
            'otp_mail_verify_token' => 'string',
            'phone' => 'string',
            'post_code' => 'string',
            'provider_avatar' => 'string',
            'seo_description' => 'text',
            'seo_title' => 'string',
            'shop_name' => 'string',
            'slug' => 'string',
            'state_id' => 'unsignedBigInteger',
            'status' => 'integer',
            'user_name' => 'string',
            'verify_token' => 'string',
        ],
        'wishlists' => [
            'product_id' => 'unsignedBigInteger',
            'user_id' => 'unsignedBigInteger',
        ],
        'withdraw_methods' => [
            'status' => 'integer',
        ],
    ];

    public function up(): void
    {
        foreach (self::NEW_TABLES as $table) {
            if (!Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $blueprint) {
                    $blueprint->id();
                    $blueprint->timestamps();
                });
            }
        }

        foreach (self::COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $missing = array_diff_key($columns, array_flip(Schema::getColumnListing($table)));
            if (!$missing) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($missing) {
                foreach ($missing as $column => $type) {
                    match ($type) {
                        'unsignedBigInteger' => $blueprint->unsignedBigInteger($column)->nullable(),
                        'integer' => $blueprint->integer($column)->nullable(),
                        'decimal' => $blueprint->decimal($column, 12, 2)->nullable(),
                        'double' => $blueprint->double($column)->nullable(),
                        'text' => $blueprint->text($column)->nullable(),
                        default => $blueprint->string($column)->nullable(),
                    };
                }
            });
        }

        $this->relaxNotNullOnEmptyTables();
    }

    /**
     * Template columns the code never sets (e.g. orders.amount_usd) are NOT NULL without a
     * default, so inserts fail on a fresh install. Make them nullable, keeping their type,
     * but only on empty tables so an existing database is never altered.
     */
    private function relaxNotNullOnEmptyTables(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $columns = DB::select("SELECT table_name AS t, column_name AS c, column_type AS type
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND is_nullable = 'NO' AND column_default IS NULL
              AND extra NOT LIKE '%auto_increment%' AND table_name <> 'migrations'");

        foreach ($columns as $column) {
            if (DB::table($column->t)->exists()) {
                continue;
            }
            DB::statement("ALTER TABLE `{$column->t}` MODIFY `{$column->c}` {$column->type} NULL");
        }
    }

    public function down(): void
    {
        // Intentionally empty: these columns may hold production data.
    }
};
