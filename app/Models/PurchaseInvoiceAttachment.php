<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PurchaseInvoiceAttachment extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
        'created_by',
    ];

    protected $appends = ['download_url', 'formatted_size'];

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('purchase-invoices.attachments.download', [
            'purchaseInvoice' => $this->purchase_invoice_id,
            'attachment' => $this->id,
        ]);
    }

    public function getFormattedSizeAttribute(): string
    {
        $size = (int) $this->file_size;

        if ($size >= 1048576) {
            return number_format($size / 1048576, 2) . ' MB';
        }

        if ($size >= 1024) {
            return number_format($size / 1024, 2) . ' KB';
        }

        return $size . ' B';
    }

    protected static function booted(): void
    {
        static::deleted(function (PurchaseInvoiceAttachment $attachment) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        });
    }
}
