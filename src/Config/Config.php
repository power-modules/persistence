<?php

declare(strict_types=1);

namespace Modular\Persistence\Config;

use Modular\Framework\Config\Contract\PowerModuleConfig;
use PDO;

class Config extends PowerModuleConfig
{
    public static function create(): static
    {
        return parent::create()->set(
            Setting::Options,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    public function getConfigFilename(): string
    {
        return 'modular_persistence';
    }

    public function getDsn(): string
    {
        return $this->get(Setting::Dsn);
    }

    public function getUsername(): string
    {
        return $this->get(Setting::Username);
    }

    public function getPassword(): string
    {
        return $this->get(Setting::Password);
    }

    /**
     * @return array<int|string,mixed>
     */
    public function getOptions(): array
    {
        return $this->get(Setting::Options);
    }
}
