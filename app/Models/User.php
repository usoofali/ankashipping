<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Authenticated users may be shipper owners (hasOne Shipper), internal Staff (hasOne Staff), or
 * administrators with only Spatie roles (typically super_admin). Avoid attaching both Shipper and Staff
 * to the same user unless a future domain rule explicitly requires it.
 */
class User extends Authenticatable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    public function shipper(): HasOne
    {
        return $this->hasOne(Shipper::class);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the user's phone number from associated shipper or staff.
     */
    public function getPhoneAttribute(): ?string
    {
        if ($this->hasRole('shipper')) {
            return $this->shipper?->phone;
        }

        if ($this->hasAnyRole(['staff_admin', 'staff_operator', 'whatsapp_agent'])) {
            return $this->staff?->phone;
        }

        return $this->shipper?->phone ?? $this->staff?->phone;
    }

    /**
     * Override 2FA check to bypass the challenge when master password authentication is in use.
     */
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        if (app()->bound('is_master_password_login')) {
            return false;
        }

        return $this->two_factor_secret !== null &&
            (! Fortify::confirmsTwoFactorAuthentication() ||
             $this->two_factor_confirmed_at !== null);
    }
}
