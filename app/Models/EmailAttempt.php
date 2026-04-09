<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmailAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmailAttempt extends Model
{
    /** @use HasFactory<EmailAttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'email_log_id',
        'exception_message',
        'smtp_response',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }
}
