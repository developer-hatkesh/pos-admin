<?php

declare(strict_types=1);

namespace App\Filament\Resources\Variations\Pages;

use App\Filament\Resources\Variations\VariationResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateVariation extends CreateRecord
{
    protected static string $resource = VariationResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::TwoExtraLarge;
    }
}
