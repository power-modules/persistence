<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema\Adapter;

use Modular\Persistence\Schema\Adapter\PostgresSchemaQueryGenerator;
use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Test\Unit\Schema\Adapter\Assets\TestSalesReportSchema;
use Modular\Persistence\Test\Unit\Schema\Adapter\Assets\TestSchemaNoPrimaryKey;
use Modular\Persistence\Test\Unit\Schema\Adapter\Assets\TestSchemaWithExpressionIndex;
use Modular\Persistence\Test\Unit\Schema\Adapter\Assets\TestSchemaWithForeignSchema;
use Modular\Persistence\Test\Unit\Schema\Adapter\Assets\TestSchemaWithIndexTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PostgresSchemaQueryGenerator::class)]
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

    public function testGenerateShouldHandleNoPrimaryKey(): void
    {
        $generator = new PostgresSchemaQueryGenerator();
        $queries = [];

        foreach ($generator->generate(TestSchemaNoPrimaryKey::Name) as $query) {
            $queries[] = $query;
        }

        self::assertCount(1, $queries);
        // Should not contain PRIMARY KEY
        self::assertStringNotContainsString('PRIMARY KEY', $queries[0]);
        self::assertSame('CREATE TABLE "no_pk_table" ("name" VARCHAR(255) NULL DEFAULT NULL, "value" INTEGER NULL DEFAULT NULL);', $queries[0]);
    }

    public function testGenerateShouldProduceCorrectIndexTypeQueries(): void
    {
        $expectedSqlFile = file_get_contents(__DIR__ . '/Assets/TestSchemaWithIndexTypes.sql');

        if ($expectedSqlFile === false) {
            throw new RuntimeException('Assets/TestSchemaWithIndexTypes.sql is not readable.');
        }

        $generator = new PostgresSchemaQueryGenerator();
        $queries = [];

        foreach ($generator->generate(TestSchemaWithIndexTypes::Id) as $query) {
            $queries[] = $query;
        }

        self::assertCount(7, $queries);
        self::assertSame(trim($expectedSqlFile), implode(PHP_EOL, $queries));
    }

    public function testGenerateShouldContainUsingClauseForNonBtreeIndexes(): void
    {
        $generator = new PostgresSchemaQueryGenerator();
        $queries = [];

        foreach ($generator->generate(TestSchemaWithIndexTypes::Id) as $query) {
            $queries[] = $query;
        }

        // GIN index should contain USING GIN
        self::assertStringContainsString('USING GIN', $queries[2]);
        // GiST index should contain USING GiST
        self::assertStringContainsString('USING GiST', $queries[3]);
        // HASH index should contain USING HASH
        self::assertStringContainsString('USING HASH', $queries[4]);
        // BRIN index should contain USING BRIN
        self::assertStringContainsString('USING BRIN', $queries[5]);
        // BTREE indexes should NOT contain USING
        self::assertStringNotContainsString('USING', $queries[1]);
        self::assertStringNotContainsString('USING', $queries[6]);
    }

    private function getSchema(): ISchema
    {
        return TestSalesReportSchema::Id;
    }

    private function getSchemaWithForeignSchema(): ISchema
    {
        return TestSchemaWithForeignSchema::Id;
    }

    public function testGenerateShouldHandleExpressionIndex(): void
    {
        $generator = new PostgresSchemaQueryGenerator();
        $queries = [];

        foreach ($generator->generate(TestSchemaWithExpressionIndex::Id) as $query) {
            $queries[] = $query;
        }

        // CREATE TABLE + regular GIN index + expression GIN index
        self::assertCount(3, $queries);

        // Regular column index — column quoted as identifier
        self::assertStringContainsString('USING GIN ("metadata")', $queries[1]);

        // Expression index — expression NOT quoted as identifier
        self::assertStringContainsString('USING GIN (("metadata"->\'keywords\'))', $queries[2]);
        self::assertStringContainsString('"idx_metadata_keywords"', $queries[2]);
    }

    public function testExpressionIndexDoesNotQuoteExpression(): void
    {
        $generator = new PostgresSchemaQueryGenerator();
        $queries = [];

        foreach ($generator->generate(TestSchemaWithExpressionIndex::Id) as $query) {
            $queries[] = $query;
        }

        // The expression index query should NOT have the expression wrapped in double quotes
        // It should be: ("metadata"->'keywords') not ("("metadata"->'keywords')")
        $expressionQuery = $queries[2];
        self::assertStringNotContainsString('("("metadata"', $expressionQuery);
    }
}
