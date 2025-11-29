<?php

declare(strict_types=1);

use Modular\Persistence\Config\Config;
use Modular\Persistence\Config\Setting;

return Config::create()
    ->set(Setting::Dsn, 'sqlite::memory:')
    ->set(Setting::Username, '')
    ->set(Setting::Password, '')
    ->set(Setting::Options, [])
;
