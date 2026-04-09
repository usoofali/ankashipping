<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailLogStatus;
use Database\Factories\EmailLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EmailLog extends Model
{
    /** @use HasFactory<EmailLogFactory> */
    use HasFactory;

    protected $fillable = [
        'mailable_class',
        'recipient_email',
        'subject',
        'body',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailLogStatus::class,
            'meta' => 'array',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(EmailAttempt::class);
    }
}
