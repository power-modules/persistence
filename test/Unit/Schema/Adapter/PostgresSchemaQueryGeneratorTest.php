<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema\Adapter;

use Modular\Persistence\Schema\Adapter\PostgresSchemaQueryGenerator;
use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Test\Unit\Schema\Adapter\Assets\TestSalesReportSchema;
use Modular\Persistence\Test\Unit\Schema\Adapter\Assets\TestSchemaWithForeignSchema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PostgresSchemaQueryGeneratorTest extends TestCase
{
    public function testGenerateShouldProduceCorrectQueries(): void
    {
        $expectedSqlFile = file_get_contents(__DIR__ . '/Assets/TestSalesReportSchema.sql');

        if ($expectedSqlFile === false) {
            throw new RuntimeException('Assets/TestSalesReportSchema.sql is not readable.');
        }

        $generator = new PostgresSchemaQueryGenerator();
        $queries = [];

        foreach ($generator->generate($this->getSchema()) as $query) {
            $queries[] = $query;
        }

        self::assertCount(3, $queries);
        self::assertSame(trim($expectedSqlFile), implode(PHP_EOL, $queries));
    }

    public function testGenerateShouldHandleForeignSchemaNames(): void
    {
        $expectedSqlFile = file_get_contents(__DIR__ . '/Assets/TestSchemaWithForeignSchema.sql');

        if ($expectedSqlFile === false) {
            throw new RuntimeException('Assets/TestSchemaWithForeignSchema.sql is not readable.');
        }

        $generator = new PostgresSchemaQueryGenerator();
        $queries = [];

        foreach ($generator->generate($this->getSchemaWithForeignSchema()) as $query) {
            $queries[] = $query;
        }

        self::assertCount(1, $queries);
        self::assertSame(trim($expectedSqlFile), implode(PHP_EOL, $queries));
    }

    private function getSchema(): ISchema
    {
        return TestSalesReportSchema::Id;
    }

    private function getSchemaWithForeignSchema(): ISchema
    {
        return TestSchemaWithForeignSchema::Id;
    }
}
