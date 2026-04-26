<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    public const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'category_id',
        'sender_type',
        'message_text',
        'decorator',
        'message_type',
        'media_url',
        'whatsapp_message_id',
        'status',
        'related_entity_id',
        'related_entity_type',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCategory::class, 'category_id');
    }

    public function relatedEntity(): MorphTo
    {
        return $this->morphTo('related_entity');
    }
}
