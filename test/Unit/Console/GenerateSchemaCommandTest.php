<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Console;

use Generator;
use Modular\Persistence\Console\GenerateSchemaCommand;
use Modular\Persistence\Schema\ISchemaQueryGenerator;
use Modular\Persistence\Test\Unit\Repository\Fixture\Schema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateSchemaCommandTest extends TestCase
{
    private string $generatedFile;

    protected function setUp(): void
    {
        $this->generatedFile = realpath(__DIR__ . '/../Repository/Fixture') . '/Schema.sql';
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
            ->with(Schema::Id)
            ->willReturn($this->generateQueries());

        $command = new GenerateSchemaCommand($queryGenerator);
        $tester = new CommandTester($command);

        $tester->execute([
            'schema-class' => Schema::class,
        ]);

        $tester->assertCommandIsSuccessful();

        self::assertFileExists($this->generatedFile);
        self::assertStringEqualsFile($this->generatedFile, "CREATE TABLE ...;\nALTER TABLE ...;");

        $output = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString(sprintf('Generated SQL for "%s"', Schema::class), $output);
        self::assertStringContainsString($this->generatedFile, $output);
    }

    public function testExecuteWithNonExistentClass(): void
    {
        $queryGenerator = $this->createMock(ISchemaQueryGenerator::class);
        $command = new GenerateSchemaCommand($queryGenerator);
        $tester = new CommandTester($command);

        $tester->execute([
            'schema-class' => 'NonExistentClass',
        ]);

        self::assertSame(1, $tester->getStatusCode());
        $output = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Class "NonExistentClass" does not exist.', $output);
    }

    public function testExecuteWithInvalidClass(): void
    {
        $queryGenerator = $this->createMock(ISchemaQueryGenerator::class);
        $command = new GenerateSchemaCommand($queryGenerator);
        $tester = new CommandTester($command);

        $tester->execute([
            'schema-class' => self::class, // This test class does not implement ISchema
        ]);

        self::assertSame(1, $tester->getStatusCode());
        $output = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Class "Modular\Persistence\Test\Unit\Console\GenerateSchemaCommandTest" must implement', $output);
        self::assertStringContainsString('"Modular\Persistence\Schema\ISchema".', $output);
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
