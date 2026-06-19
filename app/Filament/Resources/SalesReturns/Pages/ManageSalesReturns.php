<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesReturns\Pages;

use App\Enums\SalesReturnStatus;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use App\Models\SalesReturn;
use App\Services\Accounting\SalesReturnPostingService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageSalesReturns extends ManageRecords
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => SalesReturnResource::calculateTotalsFromData($data))
                ->after(function (SalesReturn $record): void {
                    if ($record->status !== SalesReturnStatus::Draft) {
                        return;
                    }

                    app(SalesReturnPostingService::class)->post($record);

                    Notification::make()
                        ->title('Sales return posted and stock restored')
                        ->success()
                        ->send();
                }),
        ];
    }
}
