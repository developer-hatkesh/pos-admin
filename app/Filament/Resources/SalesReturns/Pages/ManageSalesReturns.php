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
        $requestedStatus = SalesReturnStatus::Posted;
        $selectedSalesInvoiceIds = [];

        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data) use (&$requestedStatus, &$selectedSalesInvoiceIds): array {
                    $requestedStatus = SalesReturnStatus::tryFrom((string) ($data['status'] ?? '')) ?? SalesReturnStatus::Posted;
                    $selectedSalesInvoiceIds = SalesReturnResource::selectedSalesInvoiceIdsFromData($data);
                    $data = SalesReturnResource::prepareDataForSave($data);

                    if ($requestedStatus === SalesReturnStatus::Posted) {
                        $data['status'] = SalesReturnStatus::Draft->value;
                    }

                    return $data;
                })
                ->after(function (SalesReturn $record) use (&$requestedStatus, &$selectedSalesInvoiceIds): void {
                    if ($selectedSalesInvoiceIds !== []) {
                        $record->salesInvoices()->sync($selectedSalesInvoiceIds);
                    }

                    if ($requestedStatus !== SalesReturnStatus::Posted || $record->status !== SalesReturnStatus::Draft) {
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
