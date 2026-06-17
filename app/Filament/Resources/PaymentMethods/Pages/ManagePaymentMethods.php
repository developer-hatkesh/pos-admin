<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManagePaymentMethods extends ManageRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::None)
                ->extraModalWindowAttributes(['style' => PaymentMethodResource::FORM_MODAL_WIDTH_STYLE]),
        ];
    }
}
