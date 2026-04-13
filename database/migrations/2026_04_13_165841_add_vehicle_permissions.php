<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Permission::query()->firstOrCreate(['name' => 'vehicles.view', 'guard_name' => 'web']);
        Permission::query()->firstOrCreate(['name' => 'vehicles.create', 'guard_name' => 'web']);
        Permission::query()->firstOrCreate(['name' => 'vehicles.update', 'guard_name' => 'web']);
        Permission::query()->firstOrCreate(['name' => 'vehicles.delete', 'guard_name' => 'web']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::query()->whereIn('name', [
            'vehicles.view',
            'vehicles.create',
            'vehicles.update',
            'vehicles.delete',
        ])->delete();
    }
};
