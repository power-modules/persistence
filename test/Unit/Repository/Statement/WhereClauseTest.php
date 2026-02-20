<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Operator;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use Modular\Persistence\Repository\Statement\WhereClause;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WhereClause::class)]
final class WhereClauseTest extends TestCase
{
    public function testEmptyWhereClause(): void
    {
        $where = new WhereClause();
        self::assertSame('', $where->toSql());
        self::assertEmpty($where->getBinds());
    }

    public function testSimpleCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::equals('name', 'John'));

        self::assertSame(' WHERE (name = :w_0_name)', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('John', $binds[0]->value);
    }

    public function testExistsCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::exists('SELECT 1 FROM users WHERE id = 1'));

        self::assertSame(' WHERE (EXISTS (SELECT 1 FROM users WHERE id = 1))', $where->toSql());
        self::assertEmpty($where->getBinds());
    }

    public function testMultipleConditions(): void
    {
        $where = new WhereClause();
        $where->add(Condition::equals('name', 'John'));
        $where->add(Condition::exists('SELECT 1'));

        self::assertSame(' WHERE (name = :w_0_name) AND (EXISTS (SELECT 1))', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('John', $binds[0]->value);
    }

    public function testInCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::in('id', [1, 2, 3]));

        self::assertSame(' WHERE (id IN (:w_0_id,:w_1_id,:w_2_id))', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(3, $binds);
        self::assertSame(1, $binds[0]->value);
        self::assertSame(2, $binds[1]->value);
        self::assertSame(3, $binds[2]->value);
    }

    public function testIsNullCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::isNull('deleted_at'));

        self::assertSame(' WHERE (deleted_at IS NULL)', $where->toSql());
        self::assertEmpty($where->getBinds());
    }

    // --- Raw condition tests (Solution A) ---

    public function testRawConditionOnly(): void
    {
        $where = new WhereClause();
        $bind = Bind::json('metadata', ':kw_filter', '{"status":"active"}');
        $where->addRaw('"metadata" @> :kw_filter::jsonb', [$bind]);

        self::assertSame(' WHERE ("metadata" @> :kw_filter::jsonb)', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('{"status":"active"}', $binds[0]->value);
        self::assertSame(':kw_filter', $binds[0]->name);
    }

    public function testRawConditionAlongsideStandardConditions(): void
    {
        $where = new WhereClause();
        $where->add(Condition::equals('status', 'published'));
        $bind = Bind::json('metadata', ':kw', '{"lang":"en"}');
        $where->addRaw('"metadata" @> :kw::jsonb', [$bind]);

        self::assertSame(
            ' WHERE (status = :w_0_status) AND ("metadata" @> :kw::jsonb)',
            $where->toSql(),
        );
        $binds = $where->getBinds();
        self::assertCount(2, $binds);
        self::assertSame('published', $binds[0]->value);
        self::assertSame('{"lang":"en"}', $binds[1]->value);
    }

    public function testMultipleRawConditions(): void
    {
        $where = new WhereClause();
        $bind1 = Bind::json('metadata', ':kw1', '{"a":1}');
        $bind2 = Bind::json('metadata', ':kw2', '{"b":2}');
        $where->addRaw('"metadata" @> :kw1::jsonb', [$bind1]);
        $where->addRaw('"metadata" @> :kw2::jsonb', [$bind2]);

        self::assertSame(
            ' WHERE ("metadata" @> :kw1::jsonb) AND ("metadata" @> :kw2::jsonb)',
            $where->toSql(),
        );
        $binds = $where->getBinds();
        self::assertCount(2, $binds);
        self::assertSame(':kw1', $binds[0]->name);
        self::assertSame(':kw2', $binds[1]->name);
    }

    public function testRawConditionWithNoBinds(): void
    {
        $where = new WhereClause();
        $where->addRaw('1 = 1');

        self::assertSame(' WHERE (1 = 1)', $where->toSql());
        self::assertEmpty($where->getBinds());
    }

    public function testStandardAndMultipleRawConditions(): void
    {
        $where = new WhereClause();
        $where->add(Condition::equals('title', 'hello'));
        $where->addRaw('"data"->\'key\' IS NOT NULL');
        $bind = Bind::create('data', ':j', 'test');
        $where->addRaw('"data"->>\'field\' = :j', [$bind]);

        self::assertSame(
            ' WHERE (title = :w_0_title) AND ("data"->\'key\' IS NOT NULL) AND ("data"->>\'field\' = :j)',
            $where->toSql(),
        );
        $binds = $where->getBinds();
        self::assertCount(2, $binds);
        self::assertSame('hello', $binds[0]->value);
        self::assertSame('test', $binds[1]->value);
    }

    // --- JSONB operator tests (Solution B) ---

    public function testJsonContainsCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::jsonContains('metadata', '{"status":"active"}'));

        self::assertSame(' WHERE (metadata @> :w_0_metadata::jsonb)', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('{"status":"active"}', $binds[0]->value);
    }

    public function testJsonContainedByCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::jsonContainedBy('metadata', '{"status":"active","lang":"en"}'));

        self::assertSame(' WHERE (metadata <@ :w_0_metadata::jsonb)', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('{"status":"active","lang":"en"}', $binds[0]->value);
    }

    public function testJsonHasKeyCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::jsonHasKey('metadata', 'status'));

        self::assertSame(' WHERE (metadata ? :w_0_metadata)', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('status', $binds[0]->value);
    }

    public function testJsonHasAnyKeyCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::jsonHasAnyKey('metadata', ['status', 'lang']));

        self::assertSame(' WHERE (metadata ?| :w_0_metadata::text[])', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('{status,lang}', $binds[0]->value);
    }

    public function testJsonHasAllKeysCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::jsonHasAllKeys('metadata', ['status', 'lang']));

        self::assertSame(' WHERE (metadata ?& :w_0_metadata::text[])', $where->toSql());
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('{status,lang}', $binds[0]->value);
    }

    public function testJsonPathConditionWithEquals(): void
    {
        $where = new WhereClause();
        $where->add(Condition::jsonPath('"metadata"->>\'status\'', Operator::Equals, 'active'));

        $sql = $where->toSql();
        self::assertStringContainsString('"metadata"->>\'status\' =', $sql);
        $binds = $where->getBinds();
        self::assertCount(1, $binds);
        self::assertSame('active', $binds[0]->value);
    }

    public function testJsonContainsAlongsideStandardCondition(): void
    {
        $where = new WhereClause();
        $where->add(Condition::equals('status', 'published'));
        $where->add(Condition::jsonContains('metadata', '{"lang":"en"}'));

        self::assertSame(
            ' WHERE (status = :w_0_status) AND (metadata @> :w_1_metadata::jsonb)',
            $where->toSql(),
        );
        $binds = $where->getBinds();
        self::assertCount(2, $binds);
        self::assertSame('published', $binds[0]->value);
        self::assertSame('{"lang":"en"}', $binds[1]->value);
    }

    public function testJsonContainsWithRawConditionCombined(): void
    {
        $where = new WhereClause();
        $where->add(Condition::jsonContains('metadata', '{"status":"active"}'));
        $bind = Bind::create('data', ':custom', 'value');
        $where->addRaw('"data"->>\'key\' = :custom', [$bind]);

        self::assertSame(
            ' WHERE (metadata @> :w_0_metadata::jsonb) AND ("data"->>\'key\' = :custom)',
            $where->toSql(),
        );
        $binds = $where->getBinds();
        self::assertCount(2, $binds);
        self::assertSame('{"status":"active"}', $binds[0]->value);
        self::assertSame('value', $binds[1]->value);
    }
}
