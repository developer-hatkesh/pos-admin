<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ledgers\Pages;

use App\Filament\Resources\Ledgers\LedgerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLedgers extends ManageRecords
{
    protected static string $resource = LedgerResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
