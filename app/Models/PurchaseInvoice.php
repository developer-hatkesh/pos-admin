<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PurchaseInvoice extends Model implements HasMedia
{
    use BelongsToCompany, HasFactory, InteractsWithMedia;

    public const ATTACHMENTS_COLLECTION = 'purchase_invoice_attachments';

    protected $fillable = ['company_id', 'invoice_no', 'party_id', 'supplier_id', 'currency_id', 'invoice_date', 'due_date', 'subtotal', 'discount', 'vat_total', 'shipping', 'total', 'status', 'journal_id', 'attachment_url'];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    public static function nextInvoiceNo(int $companyId, mixed $date = null): string
    {
        return DocumentNumber::next(self::class, 'invoice_no', 'PI', $companyId);
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseInvoice $invoice): void {
            if (blank($invoice->currency_id) && $invoice->supplier_id) {
                $invoice->currency_id = Supplier::withoutGlobalScopes()->find($invoice->supplier_id)?->currency_id;
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENTS_COLLECTION)
            ->useDisk('s3')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
            ]);
    }

    public function syncAttachmentUrl(): void
    {
        $this->forceFill([
            'attachment_url' => $this->getFirstMediaUrl(self::ATTACHMENTS_COLLECTION) ?: null,
        ])->saveQuietly();
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'invoice_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function allocations()
    {
        return $this->hasMany(VoucherAllocation::class);
    }

    public function purchaseReturns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function multiPurchaseReturns()
    {
        return $this->belongsToMany(PurchaseReturn::class, 'purchase_invoice_purchase_return')->withTimestamps();
    }
}
