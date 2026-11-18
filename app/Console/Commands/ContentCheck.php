<?php

namespace App\Console\Commands;

use App\Models\FacebookPixel;
use App\Models\Flutterwave;
use App\Models\GoogleAnalytic;
use App\Models\InstamojoPayment;
use App\Models\PaypalPayment;
use App\Models\PaystackAndMollie;
use App\Models\PusherCredentail;
use App\Models\RazorpayPayment;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StripePayment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only report of live data that needs an admin's attention: test accounts and
 * prices, settings that expose personal data, outdated or sandbox configuration and
 * leftovers from the purchased template. It never changes anything.
 */
class ContentCheck extends Command
{
    protected $signature = 'esiayi:content-check {--min-price=5 : Flag active services priced below this}';

    protected $description = 'List live data that needs an admin\'s attention (read-only)';

    private array $findings = [];

    public function handle(): int
    {
        $setting = Setting::first();

        if ($setting && $setting->show_provider_contact_info == 1) {
            $this->flag('Privacy', 'Providers\' personal phone and email are shown publicly on every service page.', 'Admin > Settings: turn off "show provider contact info" unless every provider agreed to it.');
        }

        $active = Service::where(['status' => 1, 'approve_by_admin' => 1, 'is_banned' => 0]);
        $activeCount = (clone $active)->count();
        $byProvider = (clone $active)->select('provider_id', DB::raw('count(*) as total'))->groupBy('provider_id')->orderByDesc('total')->first();
        if ($activeCount >= 5 && $byProvider && $byProvider->total / $activeCount >= 0.8) {
            $provider = User::find($byProvider->provider_id);
            $this->flag('Content', sprintf('One provider ("%s") owns %d of %d live services. If this is a test or staff account, customers are booking with it.', $provider->name ?? 'unknown', $byProvider->total, $activeCount), 'Move the services to real providers, or rename the account to the business name.');
        }

        $cheap = (clone $active)->where('price', '<', (float) $this->option('min-price'))->get(['name', 'price']);
        foreach ($cheap as $service) {
            $this->flag('Content', sprintf('"%s" is live at %s%s.', $service->name, $setting->currency_icon ?? '', $service->price), 'Set the real price, or unpublish it if it is a test service.');
        }

        $both = (clone $active)->where(['make_featured' => 1, 'make_popular' => 1])->count();
        if ($both > 0 && $both === (clone $active)->where('make_featured', 1)->count()) {
            $this->flag('Content', "Every featured service is also marked popular ($both), so the home page shows the same services twice.", 'Admin > Services: vary featured and popular.');
        }

        $testUsers = User::where(function ($query) {
            $query->where('email', 'like', '%@example.%')
                ->orWhere('email', 'like', '%test%')
                ->orWhere('name', 'like', '%test%')
                ->orWhere('name', 'like', '%demo%');
        })->get(['name', 'email', 'is_provider']);
        foreach ($testUsers as $user) {
            $this->flag('Content', sprintf('Possible test account: %s <%s>%s.', $user->name, $user->email, $user->is_provider ? ' (provider)' : ''), 'Delete or deactivate it if it is not a real customer or provider.');
        }

        $analytics = GoogleAnalytic::first();
        if ($analytics && $analytics->status == 1 && str_starts_with(strtoupper((string) $analytics->analytic_id), 'UA-')) {
            $this->flag('Settings', "Google Analytics uses a Universal Analytics ID ({$analytics->analytic_id}); that product stopped collecting data in 2023.", 'Create a GA4 property and enter its G-... measurement ID.');
        }

        $pixel = FacebookPixel::first();
        if ($pixel && $pixel->status == 1 && !$pixel->app_id) {
            $this->flag('Settings', 'The Facebook Pixel is on but has no ID.', 'Enter the pixel ID or switch it off.');
        }

        $pusher = PusherCredentail::first();
        if (!$pusher || !$pusher->app_key || !$pusher->app_secret || !$pusher->app_id) {
            $this->flag('Settings', 'Pusher is not configured, so live chat only updates on refresh.', 'Admin > Settings > Pusher: enter your own app id, key, secret and cluster.');
        }

        if ($setting && $setting->logo && $setting->logo === $setting->footer_logo) {
            $this->flag('Content', 'The footer uses the same logo as the header; on the dark footer a dark logo is barely visible.', 'Upload a light version as the footer logo.');
        }

        $this->checkGatewayModes();
        $this->checkTemplateLeftovers();

        if (!$this->findings) {
            $this->info('Nothing needs attention.');
            return self::SUCCESS;
        }

        $this->table(['Area', 'Finding', 'What to do'], $this->findings);
        $this->line(count($this->findings).' item(s). This command only reads; fix them in the admin panel.');

        return self::SUCCESS;
    }

    /** Enabled gateways still on sandbox or test credentials. */
    private function checkGatewayModes(): void
    {
        $checks = [
            'Stripe' => fn () => ($g = StripePayment::first()) && $g->status == 1 && (str_starts_with((string) $g->stripe_key, 'pk_test') || str_starts_with((string) $g->stripe_secret, 'sk_test')),
            'PayPal' => fn () => ($g = PaypalPayment::first()) && $g->status == 1 && strtolower((string) $g->account_mode) !== 'live',
            'Razorpay' => fn () => ($g = RazorpayPayment::first()) && $g->status == 1 && str_starts_with((string) $g->key, 'rzp_test'),
            'Flutterwave' => fn () => ($g = Flutterwave::first()) && $g->status == 1 && str_contains((string) $g->public_key, '_TEST'),
            'Paystack' => fn () => ($g = PaystackAndMollie::first()) && $g->paystack_status == 1 && str_starts_with((string) $g->paystack_public_key, 'pk_test'),
            'Mollie' => fn () => ($g = PaystackAndMollie::first()) && $g->mollie_status == 1 && str_starts_with((string) $g->mollie_key, 'test_'),
            'Instamojo' => fn () => ($g = InstamojoPayment::first()) && $g->status == 1 && strtolower((string) $g->account_mode) === 'sandbox',
        ];

        foreach ($checks as $gateway => $isTestMode) {
            if ($isTestMode()) {
                $this->flag('Payments', "$gateway is switched on with sandbox/test credentials, so customers cannot really pay with it.", "Admin > Payment Method: enter live credentials or switch $gateway off.");
            }
        }
    }

    /** Text from the purchased template (its name and demo server) still in the database. */
    private function checkTemplateLeftovers(): void
    {
        $needles = ['aabcserv', 'kingserv', 'websolutionus'];
        $tables = ['settings', 'footers', 'email_templates', 'about_us', 'contact_pages', 'sliders', 'seo_settings', 'custom_pages', 'terms_and_conditions'];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $textColumns = collect(DB::select("SELECT column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND data_type IN ('varchar', 'text', 'mediumtext', 'longtext')", [$table]))->pluck('c');
            foreach ($textColumns as $column) {
                foreach ($needles as $needle) {
                    if (DB::table($table)->whereRaw("LOWER(`$column`) LIKE ?", ["%$needle%"])->exists()) {
                        $this->flag('Content', "$table.$column still mentions \"$needle\" (from the purchased template).", 'Replace it with ESIAYI wording in the admin panel.');
                    }
                }
            }
        }
    }

    private function flag(string $area, string $finding, string $todo): void
    {
        $this->findings[] = [$area, $finding, $todo];
    }
}
