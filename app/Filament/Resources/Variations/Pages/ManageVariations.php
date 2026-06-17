<?php

declare(strict_types=1);

namespace App\Filament\Resources\Variations\Pages;

use App\Filament\Resources\Variations\VariationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageVariations extends ManageRecords
{
    protected static string $resource = VariationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::None)
                ->extraModalWindowAttributes(['style' => VariationResource::FORM_MODAL_WIDTH_STYLE])
                ->modalSubmitActionLabel('Save')
                ->createAnother(false)
                ->modalFooterActionsAlignment(\Filament\Support\Enums\Alignment::End),
        ];
    }
}
