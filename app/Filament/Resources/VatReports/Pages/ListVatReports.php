<?php

declare(strict_types=1);

namespace App\Filament\Resources\VatReports\Pages;

use App\Filament\Resources\VatReports\VatReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVatReports extends ListRecords
{
    protected static string $resource = VatReportResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('VAT Reports'),
            'vat_sales' => Tab::make('VAT Sales')
                ->query(fn (Builder $query): Builder => VatReportResource::salesVatQuery($query)),
            'vat_purchase' => Tab::make('VAT Purchase')
                ->query(fn (Builder $query): Builder => VatReportResource::purchaseVatQuery($query)),
            'vat_return' => Tab::make('VAT Return')
                ->query(fn (Builder $query): Builder => VatReportResource::vatReturnQuery($query)),
        ];
    }
}
