<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankTransactions\Pages;

use App\Filament\Resources\BankTransactions\BankTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBankTransactions extends ManageRecords
{
    protected static string $resource = BankTransactionResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
