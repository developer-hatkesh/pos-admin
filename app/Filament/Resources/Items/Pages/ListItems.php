<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Imports\ProductItemImporter;
use App\Filament\Resources\Items\ItemResource;
use App\Support\CurrentCompany;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(ProductItemImporter::class)
                ->options(fn (): array => [
                    'company_id' => app(CurrentCompany::class)->id(),
                ]),
            CreateAction::make(),
        ];
    }
}
