<?php

declare(strict_types=1);

namespace App\Support;

final class GatepassPinNormalizer
{
    /**
     * Normalize gate pass PIN by stripping common user labels/prefixes.
     */
    public static function normalize(?string $pin): string
    {
        if ($pin === null) {
            return '';
        }

        $cleaned = trim($pin);

        // Strip common prefixes like "Pick up PIN:", "Gate pass PIN:", "PIN:", etc.
        $cleaned = preg_replace('/^(pick\s*up\s*pin|gate\s*pass\s*pin|gatepass\s*pin|gate\s*pass|pin)\s*[:=\-]?\s*/i', '', $cleaned) ?? $cleaned;

        return strtoupper(trim($cleaned));
    }

    /**
     * Check if the PIN format is valid (alphanumeric code with optional hyphens, length 2 to 20, no spaces).
     */
    public static function isValidFormat(string $pin): bool
    {
        if ($pin === '') {
            return false;
        }

        return (bool) preg_match('/^[A-Z0-9\-_]{2,20}$/i', $pin);
    }
}
