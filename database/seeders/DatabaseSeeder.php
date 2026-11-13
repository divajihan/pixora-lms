<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Roles & permissions are seeded in every environment. Demo accounts and
     * catalog data are seeded only in local/testing.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DevelopmentSeeder::class);

            return;
        }

        $this->command?->warn('Skipping DevelopmentSeeder: only runs in local/testing.');
    }
}
