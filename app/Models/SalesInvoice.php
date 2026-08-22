<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\LogsModelActivity;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SalesInvoice extends Model implements HasMedia
{
    use BelongsToCompany, HasFactory, InteractsWithMedia, LogsModelActivity;

    public const ATTACHMENTS_COLLECTION = 'sales_invoice_attachments';

    protected $fillable = ['company_id', 'invoice_no', 'party_id', 'customer_id', 'currency_id', 'invoice_date', 'due_date', 'subtotal', 'discount', 'vat_total', 'shipping', 'total', 'status', 'journal_id', 'payment_method_id', 'payment_note', 'notes', 'attachment_url'];

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
        return DocumentNumber::next(self::class, 'invoice_no', 'SI', $companyId);
    }

    protected static function booted(): void
    {
        static::creating(function (SalesInvoice $invoice): void {
            if (blank($invoice->currency_id) && $invoice->customer_id) {
                $invoice->currency_id = Customer::withoutGlobalScopes()->find($invoice->customer_id)?->currency_id;
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items()
    {
        return $this->hasMany(SalesInvoiceItem::class, 'invoice_id')->orderBy('sort_order')->orderBy('id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function allocations()
    {
        return $this->hasMany(VoucherAllocation::class);
    }

    public function journalVoucherAllocations()
    {
        return $this->hasMany(JournalVoucherAllocation::class);
    }

    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function multiSalesReturns()
    {
        return $this->belongsToMany(SalesReturn::class, 'sales_return_sales_invoice')->withTimestamps();
    }
}
