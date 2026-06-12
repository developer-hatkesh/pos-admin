<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Filament\Resources\JournalEntries\JournalEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJournalEntries extends ManageRecords
{
    protected static string $resource = JournalEntryResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
