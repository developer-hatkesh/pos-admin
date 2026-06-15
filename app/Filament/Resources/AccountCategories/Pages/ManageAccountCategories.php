<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountCategories\Pages;

use App\Filament\Resources\AccountCategories\AccountCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAccountCategories extends ManageRecords
{
    protected static string $resource = AccountCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
