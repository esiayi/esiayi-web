<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The configuration rows the app assumes exist (Setting::first(), payment gateway rows,
 * email templates, page sections...). Without them a fresh install cannot render a page.
 *
 * Only missing rows are inserted, so it is safe to run on a database that already has
 * them. Every payment gateway, tracker and social login starts switched off; configure
 * them in the admin panel.
 */
class RequiredSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->one('settings', [
            'logo' => 'uploads/website-images/logo-2022-11-06-04-53-35-7489.png',
            'footer_logo' => 'uploads/website-images/logo-2022-11-06-04-53-35-7489.png',
            'favicon' => 'uploads/website-images/favicon-2022-09-07-04-23-36-1566.png',
            'default_avatar' => 'uploads/website-images/default-avatar-2022-12-25-04-17-13-8891.jpg',
            'login_image' => 'uploads/website-images/login-page-2022-11-06-04-12-11-6638.png',
            'timezone' => 'Europe/London',
            'text_direction' => 'ltr',
            'currency_name' => 'GBP',
            'currency_icon' => '£',
            'selected_theme' => 'theme_one',
            'theme_one_color' => '#0ba3fa',
            'theme_two_color' => '#0ba3fa',
            'theme_three_color' => '#0ba3fa',
            'enable_user_register' => 1,
            'enable_multivendor' => 1,
            'enable_subscription_notify' => 1,
            'enable_save_contact_message' => 1,
            'show_provider_contact_info' => 0,
            'how_it_work_items' => '[]',
            'sidebar_lg_header' => config('app.name'),
            'sidebar_sm_header' => mb_substr(config('app.name'), 0, 2),
            'app_short_title' => config('app.name'),
            'copyright' => config('app.name'),
        ]);

        foreach (['google_recaptchas', 'tawk_chats', 'google_analytics', 'facebook_pixels', 'facebook_comments',
                  'razorpay_payments', 'flutterwaves', 'instamojo_payments', 'bank_payments', 'cookie_consents',
                  'maintainance_texts'] as $table) {
            $this->one($table, ['status' => 0]);
        }
        $this->one('paypal_payments', ['status' => 0, 'account_mode' => 'sandbox', 'currency_code' => 'GBP', 'currency_rate' => 1]);
        $this->one('stripe_payments', ['status' => 0, 'currency_code' => 'GBP', 'currency_rate' => 1]);
        $this->one('paystack_and_mollies', ['mollie_status' => 0, 'paystack_status' => 0, 'mollie_currency_rate' => 1, 'paystack_currency_rate' => 1]);
        $this->one('social_login_information', ['is_facebook' => 0, 'is_gmail' => 0]);
        $this->one('pusher_credentails', []);
        $this->one('email_configurations', ['mail_type' => 1, 'mail_host' => 'localhost', 'mail_port' => 587, 'mail_encryption' => 'tls']);
        $this->one('footers', ['first_column' => 'Important Link', 'second_column' => 'Quick Link', 'third_column' => 'Our Service']);
        $this->one('about_us', ['status' => 1]);
        $this->one('contact_pages', []);
        $this->one('terms_and_conditions', ['terms_and_condition' => '', 'privacy_policy' => '']);
        $this->one('sliders', ['title' => 'Book services in a few simple steps', 'popular_tag' => '[]', 'total_service_sold' => 0, 'status' => 1]);

        $templates = [
            1 => ['Password reset', 'Reset your password', 'Hi {{name}}, use the link below to reset your password.'],
            2 => ['Contact message', 'New contact message', 'New message from {{name}} ({{email}}, {{phone}}). Subject: {{subject}}<br>{{message}}'],
            3 => ['Subscription verification', 'Confirm your subscription', 'Please confirm your subscription using the link below.'],
            4 => ['Account verification', 'Verify your email address', 'Hi {{user_name}}, please verify your email address to finish creating your account.'],
            5 => ['Withdrawal approved', 'Your withdrawal was approved', 'Hi {{provider_name}}, your withdrawal of {{total_amount}} via {{withdraw_method}} was approved on {{approval_date}}. Charge: {{withdraw_charge}}. You will receive {{withdraw_amount}}.'],
            6 => ['Unused', 'Unused', ''],
            7 => ['Unused', 'Unused', ''],
            8 => ['Booking confirmation (customer)', 'Your booking is confirmed', 'Hi {{name}}, your booking #{{order_id}} for {{schedule_date}} is confirmed. Total: {{amount}}.'],
            9 => ['New booking (provider)', 'You have a new booking', 'Hi {{name}}, you have a new booking #{{order_id}} on {{schedule_date}}. Total: {{amount}}.'],
            10 => ['Account verification code', 'Your verification code', 'Hi {{user_name}}, your verification code is below.'],
            11 => ['Password reset code', 'Your password reset code', 'Hi {{name}}, your password reset code is below.'],
        ];
        foreach ($templates as $id => [$name, $subject, $body]) {
            $this->withId('email_templates', $id, ['name' => $name, 'subject' => $subject, 'description' => $body]);
        }

        foreach ([1 => 'Home', 2 => 'About Us', 3 => 'Contact Us', 4 => 'Page', 5 => 'Services', 6 => 'Blogs'] as $id => $page) {
            $this->withId('seo_settings', $id, ['page_name' => $page, 'seo_title' => $page, 'seo_description' => '']);
        }

        foreach ([1 => 'Services', 2 => 'Service', 3 => 'Provider', 4 => 'Blog', 5 => 'Review'] as $id => $page) {
            $this->withId('custom_paginations', $id, ['page_name' => $page, 'qty' => 12]);
        }

        foreach ([1 => ['404', 'Page not found'], 2 => ['500', 'Something went wrong'], 3 => ['505', 'Something went wrong']] as $id => [$number, $header]) {
            $this->withId('error_pages', $id, ['page_name' => "Error $number", 'page_number' => $number, 'header' => $header, 'button_text' => 'Go home']);
        }

        // Ids are fixed: HomeController reads sections, contents and menus by id.
        $sections = [1 => 'Intro', 2 => 'Categories', 3 => 'Featured services', 4 => 'Counter', 5 => 'Popular services',
            6 => 'Join as provider', 7 => 'Mobile app', 8 => 'Testimonials', 9 => 'Blog', 10 => 'Subscription',
            21 => 'Contact', 22 => 'Partners', 33 => 'How it works', 35 => 'How it works', 36 => 'How it works',
            37 => 'Counter', 38 => 'Why choose us', 39 => 'Join as provider', 40 => 'Testimonials', 41 => 'Partners'];
        foreach (range(1, 41) as $id) {
            $this->withId('section_controls', $id, ['page_name' => 'home', 'section_name' => $sections[$id] ?? "Section $id", 'status' => 1, 'qty' => 6]);
        }
        foreach ([1 => 'Our Categories', 2 => 'Featured Services', 3 => 'Popular Services', 4 => 'Testimonial', 5 => 'Our Blog'] as $id => $title) {
            $this->withId('section_contents', $id, ['title' => $title, 'description' => '', 'item_qty' => 6]);
        }
        $menus = [1 => 'Home', 2 => 'About Us', 3 => 'Services', 4 => 'Pages', 5 => 'FAQ', 6 => 'Terms And Conditions',
            7 => 'Privacy Policy', 8 => 'Custom Page', 9 => 'Blog', 10 => 'Contact Us'];
        foreach ($menus as $id => $name) {
            $this->withId('menu_visibilities', $id, ['menu_name' => $name, 'custom_name' => $name, 'status' => 1]);
        }
        foreach (range(1, 12) as $id) {
            $this->withId('breadcrumb_images', $id, ['location' => "Page $id", 'image_type' => 1, 'image' => 'uploads/website-images/banner-us-2022-09-14-02-54-22-5969.jpg']);
        }
    }

    /** Insert the singleton row of $table if it has none. */
    private function one(string $table, array $values): void
    {
        if (!Schema::hasTable($table) || DB::table($table)->exists()) {
            return;
        }
        DB::table($table)->insert($this->onlyColumns($table, $values) + ['created_at' => now(), 'updated_at' => now()]);
    }

    /** Insert the row with $id if it does not exist. */
    private function withId(string $table, int $id, array $values): void
    {
        if (!Schema::hasTable($table) || DB::table($table)->where('id', $id)->exists()) {
            return;
        }
        DB::table($table)->insert(['id' => $id] + $this->onlyColumns($table, $values) + ['created_at' => now(), 'updated_at' => now()]);
    }

    private function onlyColumns(string $table, array $values): array
    {
        return array_intersect_key($values, array_flip(Schema::getColumnListing($table)));
    }
}
