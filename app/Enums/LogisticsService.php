<?php

declare(strict_types=1);

namespace App\Enums;

enum LogisticsService: string
{
    case Air = 'air';
    case Ocean = 'ocean';
    case Road = 'road';
    case RailFreight = 'rail';
}
