<?php

declare(strict_types=1);

namespace App\Services\Accounting\Concerns;

use App\Models\Ledger;
use RuntimeException;

trait FindsLedgers
{
    protected function ledgerByCode(int $companyId, string $nominalCode): Ledger
    {
        $ledger = Ledger::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('nominal_code', $nominalCode)
            ->first();

        if (! $ledger) {
            throw new RuntimeException("Ledger {$nominalCode} is missing for company {$companyId}.");
        }

        return $ledger;
    }
}
