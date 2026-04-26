<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\WhatsApp\Models\WhatsAppCategory;
use Illuminate\Database\Seeder;

class WhatsAppCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'hashtag' => 'customer',
                'name' => 'Customer Service',
                'is_default' => true,
                'description' => 'Default category for all incoming shipper messages.',
            ],
            [
                'hashtag' => 'booking',
                'name' => 'Booking',
                'is_default' => false,
                'description' => 'Messages related to new shipment bookings.',
            ],
            [
                'hashtag' => 'title',
                'name' => 'Title Documents',
                'is_default' => false,
                'description' => 'Directives and messages related to vehicle titles.',
            ],
            [
                'hashtag' => 'bl',
                'name' => 'Bill of Lading',
                'is_default' => false,
                'description' => 'Directives related to Bill of Lading uploads.',
            ],
            [
                'hashtag' => 'dock',
                'name' => 'Dock Receipts',
                'is_default' => false,
                'description' => 'Directives related to Stamped Dock Receipts.',
            ],
            [
                'hashtag' => 'photo',
                'name' => 'Photos & Videos',
                'is_default' => false,
                'description' => 'Directives related to vehicle photos/videos.',
            ],
            [
                'hashtag' => 'other',
                'name' => 'Other Documents',
                'is_default' => false,
                'description' => 'General purpose document uploads.',
            ],
            [
                'hashtag' => 'invoice',
                'name' => 'Finance & Invoices',
                'is_default' => false,
                'description' => 'Directives for managing invoice statuses.',
            ],
            [
                'hashtag' => 'fill',
                'name' => 'Container Filling',
                'is_default' => false,
                'description' => 'Directives for marking containers as filled.',
            ],
            [
                'hashtag' => 'operations',
                'name' => 'General Operations',
                'is_default' => false,
                'description' => 'General staff operational commands.',
            ],
        ];

        foreach ($categories as $category) {
            WhatsAppCategory::updateOrCreate(
                ['hashtag' => $category['hashtag']],
                $category
            );
        }
    }
}
