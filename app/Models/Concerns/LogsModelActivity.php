<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsModelActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->activityLogName())
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept($this->activityLogExcludedAttributes())
            ->dontLogIfAttributesChangedOnly($this->activityLogIgnoredOnlyAttributes())
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogName(): string
    {
        return 'business';
    }

    protected function activityLogExcludedAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'remember_token'];
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return ['updated_at'];
    }

    protected function activityLogDescription(string $eventName): string
    {
        return sprintf('%s %s', $this->activityLogSubjectName(), $eventName);
    }

    protected function activityLogSubjectName(): string
    {
        $label = trim((string) ($this->activityLogIdentifier() ?? ''));

        if ($label !== '') {
            return class_basename(static::class).' '.$label;
        }

        return class_basename(static::class);
    }

    protected function activityLogIdentifier(): ?string
    {
        foreach (['invoice_no', 'return_no', 'voucher_no', 'reference', 'customer_code', 'supplier_code', 'item_code', 'nominal_code', 'account_code', 'name'] as $attribute) {
            if (! $this instanceof Model || ! array_key_exists($attribute, $this->getAttributes())) {
                continue;
            }

            $value = $this->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return $this instanceof Model && $this->getKey() !== null ? '#'.$this->getKey() : null;
    }
}
