<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Models;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'phone_number',
        'contact_id',
        'contact_type',
        'agent_id',
        'category_id',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function contact(): MorphTo
    {
        return $this->morphTo();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'agent_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCategory::class, 'category_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    public function menuState(): HasOne
    {
        return $this->hasOne(WhatsAppMenuState::class, 'conversation_id');
    }
}
