<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Signed guest download TTL
    |--------------------------------------------------------------------------
    |
    | Time-to-live in seconds for temporary signed URLs used in emails and
    | notifications. Anyone with the link may download until it expires.
    |
    */

    'signed_download_ttl_seconds' => (int) env('SHIPMENT_DOCUMENT_SIGNED_DOWNLOAD_TTL_SECONDS', 604800),

];
