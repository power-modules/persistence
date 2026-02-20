<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\DeleteStatement;
use Modular\Persistence\Repository\Statement\SelectStatement;
use Modular\Persistence\Repository\Statement\UpdateStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SelectStatement::class)]
#[CoversClass(UpdateStatement::class)]
#[CoversClass(DeleteStatement::class)]
final class RawConditionStatementTest extends TestCase
{
    // --- SelectStatement ---

    public function testSelectAddRawConditionAppearsInGetQuery(): void
    {
        $stmt = new SelectStatement('articles');
        $stmt->addRawCondition('"metadata" @> :kw::jsonb', [
            Bind::json('metadata', ':kw', '{"status":"active"}'),
        ]);

        $query = $stmt->getQuery();

        self::assertStringContainsString('WHERE ("metadata" @> :kw::jsonb)', $query);
        self::assertStringStartsWith('SELECT * FROM "articles"', $query);
    }

    public function testSelectAddRawConditionAppearsInCount(): void
    {
        $stmt = new SelectStatement('articles');
        $stmt->addRawCondition('"metadata" @> :kw::jsonb', [
            Bind::json('metadata', ':kw', '{"status":"active"}'),
        ]);

        $query = $stmt->count();

        self::assertStringContainsString('SELECT COUNT(*) as total_rows FROM "articles"', $query);
        self::assertStringContainsString('WHERE ("metadata" @> :kw::jsonb)', $query);
    }

    public function testSelectRawConditionBindsIncludedInGetWhereBinds(): void
    {
        $stmt = new SelectStatement('articles');
        $stmt->addCondition(Condition::equals('title', 'Test'));
        $stmt->addRawCondition('"metadata" @> :kw::jsonb', [
            Bind::json('metadata', ':kw', '{"lang":"en"}'),
        ]);

        $binds = $stmt->getWhereBinds();

        self::assertCount(2, $binds);
        self::assertSame('Test', $binds[0]->value);
        self::assertSame('{"lang":"en"}', $binds[1]->value);
    }

    public function testSelectCombinedStandardAndRawConditionsInQuery(): void
    {
        $stmt = new SelectStatement('articles');
        $stmt->addCondition(Condition::equals('status', 'published'));
        $stmt->addRawCondition('"metadata"->>\'lang\' = :lang', [
            Bind::create('lang', ':lang', 'en'),
        ]);
        $stmt->setLimit(20);
        $stmt->setStart(0);

        $query = $stmt->getQuery();

        self::assertStringContainsString('WHERE (status = :w_0_status) AND ("metadata"->>\'lang\' = :lang)', $query);
        self::assertStringContainsString('LIMIT 20 OFFSET 0', $query);
    }

    public function testSelectRawConditionWithNamespacedTable(): void
    {
        $stmt = new SelectStatement('articles', ['*'], 'my_schema');
        $stmt->addRawCondition('"data" ? :key', [
            Bind::create('data', ':key', 'status'),
        ]);

        $query = $stmt->getQuery();

        self::assertStringContainsString('FROM "my_schema"."articles"', $query);
        self::assertStringContainsString('WHERE ("data" ? :key)', $query);
    }

    public function testSelectCountWithNamespaceAndRawCondition(): void
    {
        $stmt = new SelectStatement('articles', ['*'], 'my_schema');
        $stmt->addRawCondition('"data" @> :val::jsonb', [
            Bind::json('data', ':val', '{}'),
        ]);

        $query = $stmt->count();

        self::assertStringContainsString('FROM "my_schema"."articles"', $query);
        self::assertStringContainsString('WHERE ("data" @> :val::jsonb)', $query);
    }

    // --- UpdateStatement ---

    public function testUpdateAddRawConditionAppearsInGetQuery(): void
    {
        $stmt = new UpdateStatement('articles');
        $stmt->prepareBinds(['title' => 'Updated']);
        $stmt->addRawCondition('"metadata" @> :kw::jsonb', [
            Bind::json('metadata', ':kw', '{"status":"draft"}'),
        ]);

        $query = $stmt->getQuery();

        self::assertStringContainsString('UPDATE "articles" SET', $query);
        self::assertStringContainsString('WHERE ("metadata" @> :kw::jsonb)', $query);
    }

    public function testUpdateRawConditionBindsIncluded(): void
    {
        $stmt = new UpdateStatement('articles');
        $stmt->prepareBinds(['title' => 'Updated']);
        $stmt->addCondition(Condition::equals('id', 1));
        $stmt->addRawCondition('"metadata" @> :kw::jsonb', [
            Bind::json('metadata', ':kw', '{"status":"draft"}'),
        ]);

        $whereBinds = $stmt->getWhereBinds();

        self::assertCount(2, $whereBinds);
        self::assertSame(1, $whereBinds[0]->value);
        self::assertSame('{"status":"draft"}', $whereBinds[1]->value);
    }

    // --- DeleteStatement ---

    public function testDeleteAddRawConditionAppearsInGetQuery(): void
    {
        $stmt = new DeleteStatement('articles');
        $stmt->addRawCondition('"metadata" @> :kw::jsonb', [
            Bind::json('metadata', ':kw', '{"archived":true}'),
        ]);

        $query = $stmt->getQuery();

        self::assertStringContainsString('DELETE FROM "articles"', $query);
        self::assertStringContainsString('WHERE ("metadata" @> :kw::jsonb)', $query);
    }

    public function testDeleteRawConditionBindsIncluded(): void
    {
        $stmt = new DeleteStatement('articles');
        $stmt->addCondition(Condition::equals('status', 'archived'));
        $stmt->addRawCondition('"metadata"->>\'expire\' < :now', [
            Bind::create('expire', ':now', '2025-01-01'),
        ]);

        $whereBinds = $stmt->getWhereBinds();

        self::assertCount(2, $whereBinds);
        self::assertSame('archived', $whereBinds[0]->value);
        self::assertSame('2025-01-01', $whereBinds[1]->value);
    }
}
