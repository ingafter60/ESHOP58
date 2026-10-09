<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReceiptAttachment model.
 *
 * Represents an uploaded albarán (provider delivery-note) file
 * attached to a specific receipt record.
 *
 * @property int         $id
 * @property int         $receipt_id
 * @property string      $original_name
 * @property string      $stored_name
 * @property string      $mime_type
 * @property int         $size_bytes
 * @property int|null    $uploaded_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ReceiptAttachment extends Model
{
    protected $table = 'receipt_attachments';

    protected $fillable = [
        'receipt_id',
        'original_name',
        'stored_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'receipt_id'  => 'integer',
        'size_bytes'  => 'integer',
        'uploaded_by' => 'integer',
    ];

    /** Allowed MIME types for albarán documents (application-layer whitelist). */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    /** Maximum upload size in bytes (10 MB). */
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    /** Storage sub-directory under the configured disk root. */
    public const STORAGE_SUBDIR = 'receipt_attachments';

    /**
     * The receipt this attachment belongs to.
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }

    /**
     * The user who uploaded this attachment.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Return the absolute disk path for this stored file.
     */
    public function diskPath(): string
    {
        return self::STORAGE_SUBDIR . '/' . $this->stored_name;
    }

    /**
     * Check whether the given MIME type is permitted.
     */
    public static function isAllowedMime(string $mime): bool
    {
        return in_array($mime, self::ALLOWED_MIMES, true);
    }

    /**
     * Check whether the given size in bytes is within the limit.
     */
    public static function isAllowedSize(int $bytes): bool
    {
        return $bytes > 0 && $bytes <= self::MAX_SIZE_BYTES;
    }
}
