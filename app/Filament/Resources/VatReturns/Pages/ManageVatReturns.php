<?php

declare(strict_types=1);

namespace App\Filament\Resources\VatReturns\Pages;

use App\Filament\Resources\VatReturns\VatReturnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVatReturns extends ManageRecords
{
    protected static string $resource = VatReturnResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
