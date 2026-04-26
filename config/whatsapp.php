<?php

return [
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'version' => env('WHATSAPP_VERSION', 'v18.0'),
];
