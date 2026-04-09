<?php

namespace Database\Seeders;

use App\Models\ChargeItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        ChargeItem::query()->firstOrCreate(
            ['item' => 'Storage'],
            ['description' => 'Per-day vehicle storage'],
        );

        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole('super_admin');

        if (app()->environment('local')) {
            $this->command?->info('Seeded roles; admin login: admin@example.com / password (if unchanged in UserFactory)');
        }
    }
}
