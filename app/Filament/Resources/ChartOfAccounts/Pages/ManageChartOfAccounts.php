<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChartOfAccounts\Pages;

use App\Filament\Resources\ChartOfAccounts\ChartOfAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChartOfAccounts extends ManageRecords
{
    protected static string $resource = ChartOfAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
