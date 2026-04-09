<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System accounting currency
    |--------------------------------------------------------------------------
    |
    | All ledger and invoice amounts in this application are denominated in
    | this ISO 4217 code. External references (e.g. vehicle auction APIs)
    | may still expose other currencies; normalize to this value at boundaries.
    |
    */

    'currency' => env('FINANCIAL_CURRENCY', 'USD'),

];
