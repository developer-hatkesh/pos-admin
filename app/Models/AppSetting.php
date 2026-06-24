<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $fillable = ['company_id', 'key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted:array',
        ];
    }

    public static function getValue(string $key, array $default = []): array
    {
        $query = static::query()->where('key', $key);

        if (static::hasCompanyColumn()) {
            $query->where('company_id', static::currentCompanyId());
        }

        return $query->value('value') ?? $default;
    }

    public static function setValue(string $key, array $value): void
    {
        if (! static::hasCompanyColumn()) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

            return;
        }

        static::query()->updateOrCreate(
            ['company_id' => static::currentCompanyId(), 'key' => $key],
            ['value' => $value],
        );
    }

    private static function currentCompanyId(): int
    {
        return app(CurrentCompany::class)->id() ?: 1;
    }

    private static function hasCompanyColumn(): bool
    {
        return Schema::hasTable('app_settings') && Schema::hasColumn('app_settings', 'company_id');
    }
}
