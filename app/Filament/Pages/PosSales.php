<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PosSales extends Page
{
    protected static ?string $title = 'POS Sales';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;
    protected static string|UnitEnum|null $navigationGroup = 'POS / Sales';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.placeholder';
}
