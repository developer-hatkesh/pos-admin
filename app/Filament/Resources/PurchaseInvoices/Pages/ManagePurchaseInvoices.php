<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchaseInvoices extends ManageRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
