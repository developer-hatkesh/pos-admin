<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalVouchers\Pages;

use App\Filament\Resources\JournalVouchers\JournalVoucherResource;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalVoucher extends ViewRecord
{
    protected static string $resource = JournalVoucherResource::class;
}
