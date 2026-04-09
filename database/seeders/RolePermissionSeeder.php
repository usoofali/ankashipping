<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionNames = [
            'admin.access',
            'users.manage',
            'roles.manage',
            'shipments.create',
            'shipments.view',
            'shipments.update',
            'shipments.delete',
            'prealerts.view',
            'prealerts.create',
            'prealerts.update',
            'shippers.view',
            'shippers.update',
            'shippers.delete',
            'invoices.view',
            'invoices.manage',
            'payments.manage',
            'documents.manage',
            'wallets.view',
            'carriers.view',
            'carriers.create',
            'carriers.update',
            'carriers.delete',
            'ports.view',
            'ports.create',
            'ports.update',
            'ports.delete',
            'countries.view',
            'countries.create',
            'countries.update',
            'countries.delete',
            'states.view',
            'states.create',
            'states.update',
            'states.delete',
            'cities.view',
            'cities.create',
            'cities.update',
            'cities.delete',
            'drivers.view',
            'drivers.create',
            'drivers.update',
            'drivers.delete',
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
            'workshops.view',
            'workshops.create',
            'workshops.update',
            'workshops.delete',
            'wallet_top_ups.view',
            'wallet_top_ups.create',
            'wallet_top_ups.approve',
            'wallet_top_ups.reject',
            'charge_items.view',
            'charge_items.create',
            'charge_items.update',
            'charge_items.delete',
            'payment_methods.view',
            'payment_methods.create',
            'payment_methods.update',
            'payment_methods.delete',
            'system_logs.view',
            'system_logs.manage',
            'default_shipment_settings.view',
            'default_shipment_settings.update',
        ];

        foreach ($permissionNames as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        $superAdmin = Role::query()->firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
        );
        $superAdmin->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

        $staffAdmin = Role::query()->firstOrCreate(
            ['name' => 'staff_admin', 'guard_name' => 'web'],
        );
        $staffAdmin->syncPermissions([
            'admin.access',
            'shipments.create',
            'shipments.view',
            'shipments.update',
            'shipments.delete',
            'prealerts.view',
            'prealerts.create',
            'prealerts.update',
            'shippers.view',
            'shippers.update',
            'shippers.delete',
            'invoices.view',
            'invoices.manage',
            'payments.manage',
            'documents.manage',
            'wallets.view',
            'carriers.view',
            'carriers.create',
            'carriers.update',
            'carriers.delete',
            'ports.view',
            'ports.create',
            'ports.update',
            'ports.delete',
            'countries.view',
            'countries.create',
            'countries.update',
            'countries.delete',
            'states.view',
            'states.create',
            'states.update',
            'states.delete',
            'cities.view',
            'cities.create',
            'cities.update',
            'cities.delete',
            'drivers.view',
            'drivers.create',
            'drivers.update',
            'drivers.delete',
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
            'workshops.view',
            'workshops.create',
            'workshops.update',
            'workshops.delete',
            'wallet_top_ups.view',
            'wallet_top_ups.create',
            'wallet_top_ups.approve',
            'wallet_top_ups.reject',
            'charge_items.view',
            'charge_items.create',
            'charge_items.update',
            'charge_items.delete',
            'payment_methods.view',
            'payment_methods.create',
            'payment_methods.update',
            'payment_methods.delete',
            'system_logs.view',
            'system_logs.manage',
            'default_shipment_settings.view',
            'default_shipment_settings.update',
        ]);

        $staffOperator = Role::query()->firstOrCreate(
            ['name' => 'staff_operator', 'guard_name' => 'web'],
        );
        $staffOperator->syncPermissions([
            'shipments.create',
            'shipments.view',
            'shipments.update',
            'prealerts.view',
            'prealerts.create',
            'prealerts.update',
            'shippers.view',
            'shippers.update',
            'shippers.delete',
            'documents.manage',
            'carriers.view',
            'ports.view',
            'countries.view',
            'states.view',
            'cities.view',
            'drivers.view',
            'staff.view',
            'workshops.view',
            'wallet_top_ups.view',
            'charge_items.view',
            'payment_methods.view',
            'system_logs.view',
        ]);

        $shipper = Role::query()->firstOrCreate(
            ['name' => 'shipper', 'guard_name' => 'web'],
        );
        $shipper->syncPermissions([
            'shipments.view',
            'prealerts.view',
            'prealerts.create',
            'shippers.view',
            'invoices.view',
            'payments.manage',
            'documents.manage',
            'wallets.view',
            'wallet_top_ups.view',
            'wallet_top_ups.create',
        ]);
    }
}
