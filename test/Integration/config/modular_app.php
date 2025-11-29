<?php

declare(strict_types=1);

use Modular\Framework\App\Config\Config as AppConfig;
use Modular\Framework\App\Config\Setting;

return AppConfig::create()
    ->set(Setting::AppRoot, __DIR__.'/../')
;
