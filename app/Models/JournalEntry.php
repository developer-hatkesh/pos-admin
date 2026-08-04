<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalSourceType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\LogsModelActivity;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use BelongsToCompany, HasFactory, LogsModelActivity;

    protected $fillable = ['company_id', 'entry_date', 'reference', 'description', 'source_type', 'source_id', 'created_by'];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'source_type' => JournalSourceType::class,
        ];
    }

    public static function nextReference(int $companyId): string
    {
        return DocumentNumber::next(self::class, 'reference', 'JR', $companyId);
    }

    protected static function booted(): void
    {
        static::creating(function (JournalEntry $journalEntry): void {
            if (blank($journalEntry->reference) && $journalEntry->company_id !== null) {
                $journalEntry->reference = self::nextReference($journalEntry->company_id);
            }

            $journalEntry->created_by = $journalEntry->created_by ?: auth()->id();
        });
    }

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function journalLines() { return $this->hasMany(JournalLine::class, 'journal_id'); }

    public function getDebitTotalAttribute(): string
    {
        return number_format((float) $this->journalLines()->sum('debit'), 2, '.', '');
    }

    public function getCreditTotalAttribute(): string
    {
        return number_format((float) $this->journalLines()->sum('credit'), 2, '.', '');
    }

    public function getIsBalancedAttribute(): bool
    {
        return round((float) $this->debit_total, 2) === round((float) $this->credit_total, 2);
    }
}
