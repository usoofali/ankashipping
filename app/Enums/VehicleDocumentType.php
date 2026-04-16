<?php

declare(strict_types=1);

namespace App\Enums;

enum VehicleDocumentType: string
{
    case TitleDocument = 'title-documents';
    case PhotosAndVideos = 'photos';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TitleDocument => __('Title documents'),
            self::PhotosAndVideos => __('Photos'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
