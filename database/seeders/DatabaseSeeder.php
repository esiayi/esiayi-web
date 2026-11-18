<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed what a fresh install needs to run: configuration rows and a first admin.
     * Safe to re-run; existing rows are left alone.
     */
    public function run(): void
    {
        $this->call([
            RequiredSettingsSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
