<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountClasses\Pages;

use App\Filament\Resources\AccountClasses\AccountClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAccountClasses extends ManageRecords
{
    protected static string $resource = AccountClassResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
