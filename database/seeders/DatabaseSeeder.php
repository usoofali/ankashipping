<?php

namespace Database\Seeders;

use App\Models\ChargeItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            WhatsAppCategorySeeder::class,
        ]);

        ChargeItem::query()->firstOrCreate(
            ['item' => 'Storage'],
            ['description' => 'Per-day vehicle storage'],
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'), // or use a specific password
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('super_admin');

        if (app()->environment('local')) {
            $this->command?->info('Seeded roles; admin login: admin@example.com / password (if unchanged in UserFactory)');
        }
    }
}
