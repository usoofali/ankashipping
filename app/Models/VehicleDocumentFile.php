<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * Determine if the file is an image based on its extension.
     */
    public function isImage(): bool
    {
        $filename = $this->original_name ?? $this->path;

        if (! is_string($filename) || trim($filename) === '') {
            return false;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
    }

    /**
     * Accessor for storage_path property alias.
     *
     * @return Attribute<string|null, never>
     */
    protected function storagePath(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->path,
        );
    }
}
