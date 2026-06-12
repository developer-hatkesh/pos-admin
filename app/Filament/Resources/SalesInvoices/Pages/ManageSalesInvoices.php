<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalesInvoices extends ManageRecords
{
    protected static string $resource = SalesInvoiceResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
