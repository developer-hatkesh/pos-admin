<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemSalesReports\Pages;

use App\Filament\Resources\ItemSalesReports\ItemSalesReportResource;
use App\Filament\Resources\Reports\Concerns\HasPermanentItemSalesReportFilters;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListItemSalesReports extends ListRecords
{
    use HasPermanentItemSalesReportFilters;

    protected static string $resource = ItemSalesReportResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => ItemSalesReportResource::applySalesReportSelects(
                $this->applyItemSalesFilters($query),
                $this->reportStartDate(),
                $this->reportEndDate(),
            ));
    }

    protected function getTableHeader(): View
    {
        return view('reports.item-sales.permanent-filters', [
            'searchPlaceholder' => 'Search item sales',
        ]);
    }
}
