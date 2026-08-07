<?php

use App\Providers\AppServiceProvider;
use App\Providers\CompteGamadServiceProvider;
use App\Providers\VerificationChannelConsoleServiceProvider;

return [
    AppServiceProvider::class,
    CompteGamadServiceProvider::class,
    VerificationChannelConsoleServiceProvider::class,
];
