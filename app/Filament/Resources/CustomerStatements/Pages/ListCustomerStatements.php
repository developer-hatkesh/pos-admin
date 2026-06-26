<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerStatements\Pages;

use App\Filament\Resources\CustomerStatements\CustomerStatementResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerStatements extends ListRecords
{
    protected static string $resource = CustomerStatementResource::class;
}
