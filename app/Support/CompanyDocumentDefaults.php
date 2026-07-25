<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;

class CompanyDocumentDefaults
{
    public static function notes(): string
    {
        $companyId = app(CurrentCompany::class)->id();

        if ($companyId === null) {
            return '';
        }

        return (string) (Company::query()->whereKey($companyId)->value('notes') ?? '');
    }
}
