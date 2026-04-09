<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShipmentDocumentFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShipmentDocumentFile extends Model
{
    /** @use HasFactory<ShipmentDocumentFileFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_document_id',
        'path',
        'original_name',
        'uploaded_by',
    ];

    public function shipmentDocument(): BelongsTo
    {
        return $this->belongsTo(ShipmentDocument::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
