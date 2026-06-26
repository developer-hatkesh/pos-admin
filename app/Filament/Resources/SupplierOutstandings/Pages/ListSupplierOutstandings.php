<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierOutstandings\Pages;

use App\Filament\Resources\SupplierOutstandings\SupplierOutstandingResource;
use Filament\Resources\Pages\ListRecords;

class ListSupplierOutstandings extends ListRecords
{
    protected static string $resource = SupplierOutstandingResource::class;
}
