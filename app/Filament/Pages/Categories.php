<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Categories extends Page
{
    protected static ?string $title = 'Categories';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;
    protected static string|UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.placeholder';
}
