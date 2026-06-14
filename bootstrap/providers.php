<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Propaganistas\LaravelPhone\PhoneServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    PhoneServiceProvider::class,
];
