<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierStatements\Pages;

use App\Filament\Resources\SupplierStatements\SupplierStatementResource;
use Filament\Resources\Pages\ListRecords;

class ListSupplierStatements extends ListRecords
{
    protected static string $resource = SupplierStatementResource::class;
}
