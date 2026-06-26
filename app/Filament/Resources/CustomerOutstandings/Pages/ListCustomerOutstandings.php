<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerOutstandings\Pages;

use App\Filament\Resources\CustomerOutstandings\CustomerOutstandingResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerOutstandings extends ListRecords
{
    protected static string $resource = CustomerOutstandingResource::class;
}
