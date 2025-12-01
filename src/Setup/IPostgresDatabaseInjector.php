<?php

declare(strict_types=1);

namespace Modular\Persistence\Setup;

use Modular\Framework\PowerModule\Contract\PowerModuleSetup;
use Modular\Framework\PowerModule\Setup\PowerModuleSetupDto;
use Modular\Framework\PowerModule\Setup\SetupPhase;
use Modular\Persistence\Database\IPostgresDatabase;

final readonly class IPostgresDatabaseInjector implements PowerModuleSetup
{
    public function setup(PowerModuleSetupDto $powerModuleSetupDto): void
    {
        if ($powerModuleSetupDto->setupPhase !== SetupPhase::Post) {
            return;
        }

        $database = $powerModuleSetupDto->rootContainer->get(IPostgresDatabase::class);
        $powerModuleSetupDto->moduleContainer->set(IPostgresDatabase::class, $database);
    }
}
