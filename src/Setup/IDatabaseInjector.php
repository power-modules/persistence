<?php

declare(strict_types=1);

namespace Modular\Persistence\Setup;

use Modular\Framework\PowerModule\Contract\PowerModuleSetup;
use Modular\Framework\PowerModule\Setup\PowerModuleSetupDto;
use Modular\Framework\PowerModule\Setup\SetupPhase;
use Modular\Persistence\Database\IDatabase;

final readonly class IDatabaseInjector implements PowerModuleSetup
{
    public function setup(PowerModuleSetupDto $powerModuleSetupDto): void
    {
        if ($powerModuleSetupDto->setupPhase !== SetupPhase::Post) {
            return;
        }

        $database = $powerModuleSetupDto->rootContainer->get(IDatabase::class);
        $powerModuleSetupDto->moduleContainer->set(IDatabase::class, $database);
    }
}
