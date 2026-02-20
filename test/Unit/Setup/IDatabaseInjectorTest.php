<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Setup;

use Modular\Framework\App\Config\Config;
use Modular\Framework\Container\ConfigurableContainerInterface;
use Modular\Framework\PowerModule\Contract\PowerModule;
use Modular\Framework\PowerModule\Setup\PowerModuleSetupDto;
use Modular\Framework\PowerModule\Setup\SetupPhase;
use Modular\Persistence\Database\IDatabase;
use Modular\Persistence\Setup\IDatabaseInjector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IDatabaseInjector::class)]
class IDatabaseInjectorTest extends TestCase
{
    public function testSetupDoesNothingWhenPhaseIsNotPost(): void
    {
        $rootContainer = $this->createMock(ConfigurableContainerInterface::class);
        $moduleContainer = $this->createMock(ConfigurableContainerInterface::class);
        $powerModule = $this->createStub(PowerModule::class);
        $config = $this->createStub(Config::class);

        $dto = new PowerModuleSetupDto(
            SetupPhase::Pre,
            $powerModule,
            $rootContainer,
            $moduleContainer,
            $config,
        );

        $rootContainer->expects($this->never())->method('get');
        $moduleContainer->expects($this->never())->method('set');

        $injector = new IDatabaseInjector();
        $injector->setup($dto);
    }

    public function testSetupInjectsDatabaseWhenPhaseIsPost(): void
    {
        $rootContainer = $this->createMock(ConfigurableContainerInterface::class);
        $moduleContainer = $this->createMock(ConfigurableContainerInterface::class);
        $powerModule = $this->createStub(PowerModule::class);
        $config = $this->createStub(Config::class);
        $database = $this->createStub(IDatabase::class);

        $dto = new PowerModuleSetupDto(
            SetupPhase::Post,
            $powerModule,
            $rootContainer,
            $moduleContainer,
            $config,
        );

        $rootContainer->expects($this->once())
            ->method('get')
            ->with(IDatabase::class)
            ->willReturn($database);

        $moduleContainer->expects($this->once())
            ->method('set')
            ->with(IDatabase::class, $database);

        $injector = new IDatabaseInjector();
        $injector->setup($dto);
    }
}
