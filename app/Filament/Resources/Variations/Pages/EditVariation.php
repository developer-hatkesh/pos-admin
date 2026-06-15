<?php

declare(strict_types=1);

namespace App\Filament\Resources\Variations\Pages;

use App\Filament\Resources\Variations\VariationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditVariation extends EditRecord
{
    protected static string $resource = VariationResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::TwoExtraLarge;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
