<?php

namespace Database\Seeders;

use App\Models\Port;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = Warehouse::factory()->count(3)->create();

        // Link the first port to the first warehouse for testing
        $port = Port::first();
        if ($port) {
            $port->update(['warehouse_id' => $warehouses->first()->id]);
        }
    }
}
