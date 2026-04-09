<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Application-wide settings. The logo attribute stores a base64-encoded image,
 * optionally as a full data URL (e.g. data:image/png;base64,...).
 */
final class SystemSetting extends Model
{
    /** @use HasFactory<SystemSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_name',
        'logo',
        'logo_path',
        'address',
        'phone',
        'email',
        'zipcode',
        'country_id',
        'state_id',
        'city_id',
        'auction_api_key',
        'whatsapp_api_key',
        'tracking_delivery_prefix',
        'tracking_digits',
        'tracking_number_type',
        'tracking_random_digits',
        'preferred_mailer',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auction_api_key' => 'encrypted',
            'whatsapp_api_key' => 'encrypted',
            'tracking_digits' => 'integer',
            'tracking_random_digits' => 'integer',
        ];
    }

    /**
     * Singleton row for application-wide settings.
     */
    public static function current(): self
    {
        $existing = self::query()->first();

        if ($existing instanceof self) {
            return $existing;
        }

        return self::query()->create([]);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function logoSrcForWeb(): ?string
    {
        if (is_string($this->logo_path) && trim($this->logo_path) !== '') {
            return Storage::url(trim($this->logo_path));
        }

        if (! is_string($this->logo) || trim($this->logo) === '') {
            return null;
        }

        $logo = trim($this->logo);

        if (Str::startsWith($logo, 'data:image/')) {
            return $logo;
        }

        if (Str::startsWith($logo, ['http://', 'https://'])) {
            return $logo;
        }

        if (Str::startsWith($logo, '/')) {
            return $logo;
        }

        return Storage::url($logo);
    }

    public function logoSrcForEmail(): ?string
    {
        if (is_string($this->logo_path) && trim($this->logo_path) !== '') {
            return url(Storage::url(trim($this->logo_path)));
        }

        if (! is_string($this->logo) || trim($this->logo) === '') {
            return null;
        }

        $logo = trim($this->logo);

        if (Str::startsWith($logo, ['http://', 'https://'])) {
            return $logo;
        }

        if (Str::startsWith($logo, '/')) {
            return url($logo);
        }

        return null;
    }

    public function logoBase64(): ?string
    {
        $path = null;

        if (is_string($this->logo_path) && trim($this->logo_path) !== '') {
            $path = Storage::disk('public')->path(trim($this->logo_path));
        } elseif (is_string($this->logo) && trim($this->logo) !== '') {
            $logo = trim($this->logo);
            if (Str::startsWith($logo, 'data:image/')) {
                return $logo;
            }
            if (! Str::startsWith($logo, ['http://', 'https://', '/'])) {
                $path = Storage::disk('public')->path($logo);
            }
        }

        if ($path && file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);

            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        return null;
    }

    /**
     * Resolve the correct mailer for a given purpose.
     *
     * Stack A (Hostinger SMTP): Returns the purpose-specific account.
     *   e.g. 'operations' → operations@ankshipping.com
     *        'services'   → services@ankshipping.com
     *        'newsletter' → roundrobin across news1/2/3
     *
     * Stack B (Google Workspace): All mail routes through the single google mailer.
     */
    public function getMailerFor(string $purpose): string
    {
        if ($this->preferred_mailer === 'google') {
            return 'google';
        }

        // Hostinger stack (or default): use the purpose-specific mailer name directly.
        // This resolves to one of: operations, booking, services, accounts, noreply, newsletter
        return $purpose;
    }
}
