<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesReturnRegisters\Pages;

use App\Filament\Resources\SalesReturnRegisters\SalesReturnRegisterResource;
use Filament\Resources\Pages\ListRecords;

class ListSalesReturnRegisters extends ListRecords
{
    protected static string $resource = SalesReturnRegisterResource::class;
}
