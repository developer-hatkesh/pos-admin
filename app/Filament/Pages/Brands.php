<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Brands extends Page
{
    protected static ?string $title = 'Brands';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.placeholder';
}
