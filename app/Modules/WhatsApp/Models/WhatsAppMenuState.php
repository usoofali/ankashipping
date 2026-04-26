<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMenuState extends Model
{
    protected $table = 'whatsapp_menu_states';

    protected $fillable = [
        'conversation_id',
        'current_step',
        'data_payload',
    ];

    protected $casts = [
        'data_payload' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }
}
