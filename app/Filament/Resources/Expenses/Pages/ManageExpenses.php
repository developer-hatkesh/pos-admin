<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses\Pages;

use App\Enums\ExpenseStatus;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use App\Services\Accounting\ExpensePaymentService;
use App\Services\Accounting\ExpensePostingService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        $requestedStatus = ExpenseStatus::Posted;

        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data) use (&$requestedStatus): array {
                    $requestedStatus = ExpenseStatus::tryFrom((string) ($data['status'] ?? '')) ?? ExpenseStatus::Posted;

                    ExpenseResource::syncGrandTotalFromData($data);

                    if (in_array($requestedStatus, [ExpenseStatus::Posted, ExpenseStatus::Paid], true)) {
                        $data['status'] = ExpenseStatus::Draft->value;
                    }

                    return $data;
                })
                ->after(function (Expense $record) use (&$requestedStatus): void {
                    if ($requestedStatus === ExpenseStatus::Posted && $record->status === ExpenseStatus::Draft) {
                        app(ExpensePostingService::class)->post($record);

                        Notification::make()
                            ->title('Expense posted')
                            ->success()
                            ->send();
                    }

                    if ($requestedStatus === ExpenseStatus::Paid) {
                        app(ExpensePaymentService::class)->pay(
                            $record,
                            (int) $record->payment_bank_account_id,
                            $record->payment_date,
                        );

                        Notification::make()
                            ->title('Expense paid and payment voucher created')
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}
