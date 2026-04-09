<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Prealert;
use App\Models\User;

final class PrealertPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->can('prealerts.view')) {
            return false;
        }

        return $user->staff()->exists() || $user->shipper !== null;
    }

    public function view(User $user, Prealert $prealert): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->can('prealerts.view')) {
            return false;
        }

        if ($user->staff()->exists()) {
            return true;
        }

        return $user->shipper !== null && $user->shipper->is($prealert->shipper);
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->can('prealerts.create')) {
            return false;
        }

        return $user->shipper !== null || $user->staff()->exists();
    }

    public function update(User $user, Prealert $prealert): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->staff()->exists() && $user->can('prealerts.update');
    }

    public function delete(User $user, Prealert $prealert): bool
    {
        return $this->update($user, $prealert);
    }
}
