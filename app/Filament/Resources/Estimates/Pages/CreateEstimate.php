<?php

declare(strict_types=1);

namespace App\Filament\Resources\Estimates\Pages;

use App\Filament\Resources\Estimates\EstimateResource;
use App\Support\CurrentCompany;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateEstimate extends CreateRecord
{
    protected static string $resource = EstimateResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = EstimateResource::calculateTotalsFromData($data);
        $data['estimate_no'] = EstimateResource::nextEstimateNumber($data['company_id'] ?? app(CurrentCompany::class)->id());

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return EstimateResource::getUrl('index');
    }
}
