<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InlandRouteRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'pickup_location',
        'origin_port_id',
        'latest_rate',
        'average_rate',
        'min_rate',
        'max_rate',
        'shipment_count',
        'last_shipped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latest_rate' => 'decimal:2',
            'average_rate' => 'decimal:2',
            'min_rate' => 'decimal:2',
            'max_rate' => 'decimal:2',
            'shipment_count' => 'integer',
            'last_shipped_at' => 'datetime',
        ];
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    /**
     * Format a raw pickup location string for display in the view.
     * Capitalizes state acronyms (e.g., 'LA', 'IL', 'TX') and auction acronyms (e.g., 'IAA', 'NCS', 'DC')
     * in uppercase while preserving readable title-casing for cities and descriptions.
     */
    public static function formatLocation(?string $location): string
    {
        if ($location === null || trim($location) === '') {
            return '';
        }

        $formatted = ucwords(strtolower(trim($location)));

        // 1. Acronym/State prefix before hyphen: e.g. "sc - north" -> "SC - North", "il - chicago" -> "IL - Chicago", "*ncs - central" -> "*NCS - Central"
        $formatted = (string) preg_replace_callback('/^(\*?[a-z]{2,4})\s*-\s*/i', function (array $matches): string {
            return strtoupper($matches[1]).' - ';
        }, $formatted);

        // 2. US State abbreviation before 5-digit postal zip code: e.g. "il 60411" -> "IL 60411", "pa 17202" -> "PA 17202"
        $usStates = [
            'al', 'ak', 'az', 'ar', 'ca', 'co', 'ct', 'de', 'fl', 'ga',
            'hi', 'id', 'il', 'in', 'ia', 'ks', 'ky', 'la', 'me', 'md',
            'ma', 'mi', 'mn', 'ms', 'mo', 'mt', 'ne', 'nv', 'nh', 'nj',
            'nm', 'ny', 'nc', 'nd', 'oh', 'ok', 'or', 'pa', 'ri', 'sc',
            'sd', 'tn', 'tx', 'ut', 'vt', 'va', 'wa', 'wv', 'wi', 'wy',
            'dc', 'pr', 'vi', 'gu',
        ];

        $formatted = (string) preg_replace_callback('/\b([a-z]{2})\s+(\d{5})\b/i', function (array $matches) use ($usStates): string {
            if (in_array(strtolower($matches[1]), $usStates, true)) {
                return strtoupper($matches[1]).' '.$matches[2];
            }

            return $matches[0];
        }, $formatted);

        // 3. State abbreviation bounded by commas: e.g. ", ny," -> ", NY,", ", ma," -> ", MA,"
        $formatted = (string) preg_replace_callback('/,\s*([a-z]{2})\s*,/i', function (array $matches) use ($usStates): string {
            if (in_array(strtolower($matches[1]), $usStates, true)) {
                return ', '.strtoupper($matches[1]).',';
            }

            return $matches[0];
        }, $formatted);

        // 4. Standalone acronyms anywhere: "dc", "iaa", "ncs", "sw", "nw", "se", "ne", "u.s."
        $standaloneAcronyms = ['dc', 'iaa', 'ncs', 'sw', 'nw', 'se', 'ne', 'u.s.'];
        foreach ($standaloneAcronyms as $acronym) {
            $formatted = (string) preg_replace_callback('/\b'.preg_quote($acronym, '/').'\b/i', function (array $matches): string {
                return strtoupper($matches[0]);
            }, $formatted);
        }

        return $formatted;
    }

    public function getFormattedLocationAttribute(): string
    {
        return self::formatLocation($this->pickup_location);
    }

    public function getTrendPercentageAttribute(): float
    {
        if ((float) $this->average_rate <= 0.0) {
            return 0.0;
        }

        $diff = (float) $this->latest_rate - (float) $this->average_rate;

        return round(($diff / (float) $this->average_rate) * 100, 1);
    }
}
