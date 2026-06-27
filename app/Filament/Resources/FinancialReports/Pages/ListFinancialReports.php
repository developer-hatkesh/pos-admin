<?php

declare(strict_types=1);

namespace App\Filament\Resources\FinancialReports\Pages;

use App\Filament\Resources\FinancialReports\FinancialReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFinancialReports extends ListRecords
{
    protected static string $resource = FinancialReportResource::class;

    public function getTabs(): array
    {
        return [
            'trial_balance' => Tab::make('Trial Balance')
                ->query(fn (Builder $query): Builder => FinancialReportResource::trialBalanceQuery($query)),
            'profit_loss' => Tab::make('Profit & Loss')
                ->query(fn (Builder $query): Builder => FinancialReportResource::profitLossQuery($query)),
            'balance_sheet' => Tab::make('Balance Sheet')
                ->query(fn (Builder $query): Builder => FinancialReportResource::balanceSheetQuery($query)),
        ];
    }
}
