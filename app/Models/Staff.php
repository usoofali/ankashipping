<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\WhatsApp\Models\WhatsAppCategory;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'user_id',
        'job_title',
        'phone', // unique
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappCategories(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppCategory::class, 'staff_whatsapp_category', 'staff_id', 'whatsapp_category_id');
    }
}
