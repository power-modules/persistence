<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Console;

use Modular\Persistence\Console\MakeEntityCommand;
use Modular\Persistence\Console\MakeHydratorCommand;
use Modular\Persistence\Console\MakeRepositoryCommand;
use Modular\Persistence\Console\MakeSchemaCommand;
use Modular\Persistence\Test\Unit\Console\Fixtures\TestUserSchema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class MakeCommandsTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/persistence_test_' . uniqid();
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    public function testMakeSchema(): void
    {
        $command = new MakeSchemaCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => 'ProductSchema',
            '--folder' => $this->tempDir . '/Schema',
            '--table' => 'products',
            '--namespace' => 'App\Schema',
        ]);

        $tester->assertCommandIsSuccessful();

        $file = $this->tempDir . '/Schema/ProductSchema.php';
        self::assertFileExists($file);
        self::assertFileEquals(__DIR__ . '/Fixtures/Expected/ProductSchema.php', $file);
    }

    public function testMakeEntity(): void
    {
        $command = new MakeEntityCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => 'TestUser',
            '--schema' => TestUserSchema::class,
            '--folder' => $this->tempDir . '/Domain',
            '--namespace' => 'App\Domain',
        ]);

        $tester->assertCommandIsSuccessful();

        $file = $this->tempDir . '/Domain/TestUser.php';
        self::assertFileExists($file);
        self::assertFileEquals(__DIR__ . '/Fixtures/Expected/TestUser.php', $file);
    }

    public function testMakeHydrator(): void
    {
        $command = new MakeHydratorCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => 'TestUserHydrator',
            '--schema' => TestUserSchema::class,
            '--entity' => 'App\Domain\TestUser',
            '--folder' => $this->tempDir . '/Hydrator',
            '--namespace' => 'App\Hydrator',
        ]);

        $tester->assertCommandIsSuccessful();

        $file = $this->tempDir . '/Hydrator/TestUserHydrator.php';
        self::assertFileExists($file);
        self::assertFileEquals(__DIR__ . '/Fixtures/Expected/TestUserHydrator.php', $file);
    }

    public function testMakeRepository(): void
    {
        $command = new MakeRepositoryCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => 'TestUserRepository',
            '--hydrator' => 'App\Hydrator\TestUserHydrator',
            '--schema' => TestUserSchema::class,
            '--folder' => $this->tempDir . '/Repository',
            '--namespace' => 'App\Repository',
        ]);

        $tester->assertCommandIsSuccessful();

        $file = $this->tempDir . '/Repository/TestUserRepository.php';
        self::assertFileExists($file);
        self::assertFileEquals(__DIR__ . '/Fixtures/Expected/TestUserRepository.php', $file);
    }
}
