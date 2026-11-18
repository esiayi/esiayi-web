<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the first admin on a fresh install (skipped if any admin exists).
 * Set ADMIN_EMAIL and ADMIN_PASSWORD, or a random password is generated and printed once.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (Admin::query()->exists()) {
            return;
        }

        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD') ?: Str::password(16);

        $admin = new Admin();
        $admin->name = 'Admin';
        $admin->email = $email;
        $admin->password = Hash::make($password);
        $admin->status = 1;
        $admin->admin_type = 1;
        $admin->save();

        $this->command?->info("Admin created: {$email}".(env('ADMIN_PASSWORD') ? '' : " / password: {$password} (change it after logging in)"));
    }
}
