<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRegisters\Pages;

use App\Filament\Resources\PurchaseRegisters\PurchaseRegisterResource;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseRegisters extends ListRecords
{
    protected static string $resource = PurchaseRegisterResource::class;
}
