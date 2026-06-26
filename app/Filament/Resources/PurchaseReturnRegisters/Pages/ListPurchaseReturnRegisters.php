<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseReturnRegisters\Pages;

use App\Filament\Resources\PurchaseReturnRegisters\PurchaseReturnRegisterResource;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseReturnRegisters extends ListRecords
{
    protected static string $resource = PurchaseReturnRegisterResource::class;
}
