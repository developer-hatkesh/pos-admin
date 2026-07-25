<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalVouchers\Pages;

use App\Filament\Resources\JournalVouchers\JournalVoucherResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJournalVouchers extends ListRecords
{
    protected static string $resource = JournalVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
