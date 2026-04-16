<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VehicleDocumentFile extends Model
{
    protected $fillable = [
        'vehicle_document_id',
        'path',
        'original_name',
        'uploaded_by',
    ];

    /**
     * @return BelongsTo<VehicleDocument, $this>
     */
    public function vehicleDocument(): BelongsTo
    {
        return $this->belongsTo(VehicleDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
