<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncomeStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Income extends Model implements HasMedia
{
    use BelongsToCompany, HasFactory, InteractsWithMedia;

    public const ATTACHMENTS_COLLECTION = 'income_attachments';

    protected $fillable = [
        'company_id',
        'voucher_no',
        'income_date',
        'category',
        'sub_total_amount',
        'tax_amount',
        'grand_total_amount',
        'status',
        'notes',
        'attachment_url',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'income_date' => 'date',
            'sub_total_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total_amount' => 'decimal:2',
            'status' => IncomeStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Income $income): void {
            if (blank($income->voucher_no) && $income->company_id !== null) {
                $income->voucher_no = self::nextVoucherNo($income->company_id, $income->income_date);
            }

            $income->created_by = $income->created_by ?: auth()->id();
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENTS_COLLECTION)
            ->singleFile()
            ->useDisk('s3')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
            ]);
    }

    public function syncAttachmentUrl(): void
    {
        $this->forceFill([
            'attachment_url' => $this->getFirstMediaUrl(self::ATTACHMENTS_COLLECTION) ?: null,
        ])->saveQuietly();
    }

    public static function nextVoucherNo(int $companyId, mixed $date = null): string
    {
        $incomeDate = filled($date) ? Carbon::parse($date) : today();
        $prefix = 'INC-'.$incomeDate->format('Ymd').'-';
        $latest = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('voucher_no', 'like', $prefix.'%')
            ->orderByDesc('voucher_no')
            ->value('voucher_no');

        $next = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations()
    {
        return $this->hasMany(VoucherAllocation::class);
    }
}
