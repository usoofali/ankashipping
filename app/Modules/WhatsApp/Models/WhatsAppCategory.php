<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Models;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCategory extends Model
{
    protected $table = 'whatsapp_categories';

    protected $fillable = [
        'name',
        'hashtag',
        'is_default',
        'description',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_whatsapp_category', 'whatsapp_category_id', 'staff_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class, 'category_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'category_id');
    }
}
