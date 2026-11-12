<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\SuperadminPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    SuperadminPanelProvider::class,
    FortifyServiceProvider::class,
];
