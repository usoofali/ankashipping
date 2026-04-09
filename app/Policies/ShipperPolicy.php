<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shipper;
use App\Models\User;

final class ShipperPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->can('shippers.view')) {
            return false;
        }

        return $user->staff()->exists() || $user->shipper()->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function view(User $user, Shipper $shipper): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->can('shippers.view')) {
            return false;
        }

        if ($user->staff()->exists()) {
            return true;
        }

        if ($user->shipper?->is($shipper)) {
            return true;
        }

        return false;
    }

    public function update(User $user, Shipper $shipper): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->can('shippers.update')) {
            return false;
        }

        return $user->staff()->exists();
    }

    public function delete(User $user, Shipper $shipper): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->can('shippers.delete')) {
            return false;
        }

        return $user->staff()->exists();
    }
}
