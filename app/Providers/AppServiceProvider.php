<?php

namespace App\Providers;

use App\Models\PusherCredentail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->usePusherSettingsFromAdmin();
    }

    /**
     * Realtime chat credentials are managed in Admin > Settings and stored in the database,
     * so apply them to the broadcaster instead of relying on .env.
     */
    private function usePusherSettingsFromAdmin()
    {
        try {
            $pusher = PusherCredentail::first();
        } catch (\Throwable $e) {
            return; // database not ready yet (fresh install, or artisan before migrate)
        }

        if (!$pusher || !$pusher->app_key) {
            return;
        }

        config([
            'broadcasting.connections.pusher.key' => $pusher->app_key,
            'broadcasting.connections.pusher.secret' => $pusher->app_secret,
            'broadcasting.connections.pusher.app_id' => $pusher->app_id,
            'broadcasting.connections.pusher.options.host' => 'api-'.$pusher->app_cluster.'.pusher.com',
            'broadcasting.connections.pusher.options.cluster' => $pusher->app_cluster,
        ]);
    }
}
