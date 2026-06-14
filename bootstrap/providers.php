<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\TelescopeServiceProvider;

return array_values(array_filter([
    AppServiceProvider::class,
    AdminPanelProvider::class,
    class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)
        ? TelescopeServiceProvider::class
        : null,
]));
