<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Unit\Console;

use Generator;
use Modular\Persistence\Console\GenerateSchemaCommand;
use Modular\Persistence\Schema\Contract\ISchemaQueryGenerator;
use Modular\Persistence\Tests\Unit\Fixture\EmployeeSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(GenerateSchemaCommand::class)]
final class GenerateSchemaCommandTest extends TestCase
{
    private string $generatedFile;

    protected function setUp(): void
    {
        $this->generatedFile = realpath(__DIR__ . '/../Fixture') . '/EmployeeSchema.sql';
        if (file_exists($this->generatedFile)) {
            unlink($this->generatedFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->generatedFile)) {
            unlink($this->generatedFile);
        }
    }

    public function testExecute(): void
    {
        $queryGenerator = $this->createMock(ISchemaQueryGenerator::class);
        $queryGenerator->expects(self::once())
            ->method('generate')
            ->with(EmployeeSchema::Id)
            ->willReturn($this->generateQueries());

        $command = new GenerateSchemaCommand($queryGenerator);
        $tester = new CommandTester($command);

        $tester->execute([
            'target' => EmployeeSchema::class,
        ]);

        $tester->assertCommandIsSuccessful();

        self::assertFileExists($this->generatedFile);
        self::assertStringEqualsFile($this->generatedFile, "CREATE TABLE ...;\nALTER TABLE ...;");

        $output = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString(sprintf('Generated SQL for "%s"', EmployeeSchema::class), $output);
    }

    public function testExecuteWithDirectory(): void
    {
        $queryGenerator = $this->createMock(ISchemaQueryGenerator::class);
        $queryGenerator->expects(self::once())
            ->method('generate')
            ->with(EmployeeSchema::Id)
            ->willReturn($this->generateQueries());

        $command = new GenerateSchemaCommand($queryGenerator);
        $tester = new CommandTester($command);

        $directory = realpath(__DIR__ . '/../Fixture');

        $tester->execute([
            'target' => $directory,
        ]);

        $tester->assertCommandIsSuccessful();

        self::assertFileExists($this->generatedFile);
        self::assertStringEqualsFile($this->generatedFile, "CREATE TABLE ...;\nALTER TABLE ...;");

        $output = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString(sprintf('Generated SQL for "%s"', EmployeeSchema::class), $output);
    }

    public function testExecuteWithNonExistentClass(): void
    {
        $queryGenerator = $this->createStub(ISchemaQueryGenerator::class);
        $command = new GenerateSchemaCommand($queryGenerator);
        $tester = new CommandTester($command);

        $tester->execute([
            'target' => 'NonExistentClass',
        ]);

        self::assertSame(1, $tester->getStatusCode());
        $output = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Class or directory "NonExistentClass" does not exist.', $output);
    }

    public function testExecuteWithInvalidClass(): void
    {
        $queryGenerator = $this->createStub(ISchemaQueryGenerator::class);
        $command = new GenerateSchemaCommand($queryGenerator);
        $tester = new CommandTester($command);

        $tester->execute([
            'target' => self::class,
        ]);

        self::assertSame(1, $tester->getStatusCode());
        $output = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('must implement', $output);
        self::assertStringContainsString('ISchema', $output);
    }

    /**
     * @return Generator<string>
     */
    private function generateQueries(): Generator
    {
        yield 'CREATE TABLE ...;';
        yield 'ALTER TABLE ...;';
    }
}
