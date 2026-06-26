<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesRegisters\Pages;

use App\Filament\Resources\SalesRegisters\SalesRegisterResource;
use Filament\Resources\Pages\ListRecords;

class ListSalesRegisters extends ListRecords
{
    protected static string $resource = SalesRegisterResource::class;
}
