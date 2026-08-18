<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Statement;

use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\ConditionGroup;
use Modular\Persistence\Repository\Expression;
use Modular\Persistence\Repository\Statement\BindCollector;
use Modular\Persistence\Repository\Statement\ConditionRenderer;
use Modular\Persistence\Repository\Statement\SelectStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConditionRenderer::class)]
#[CoversClass(BindCollector::class)]
final class ConditionRendererTest extends TestCase
{
    private ConditionRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ConditionRenderer();
    }

    public function testRenderLiteralEquals(): void
    {
        $sql = $this->renderer->render(Condition::equals('name', 'Alice'));

        self::assertSame("name = 'Alice'", $sql);
    }

    public function testRenderParameterizedEquals(): void
    {
        $collector = new BindCollector();
        $sql = $this->renderer->render(Condition::equals('name', 'Alice'), null, $collector);

        self::assertSame('name = :w_0_name', $sql);
        self::assertCount(1, $collector->getBinds());
        self::assertSame('Alice', $collector->getBinds()[0]->value);
    }

    public function testRenderWithColumnResolver(): void
    {
        $resolver = fn (string $col) => '"t"."' . $col . '"';
        $sql = $this->renderer->render(Condition::equals('name', 'Alice'), $resolver);

        self::assertSame('"t"."name" = \'Alice\'', $sql);
    }

    public function testRenderInLiteral(): void
    {
        $sql = $this->renderer->render(Condition::in('status', ['active', 'pending']));

        self::assertSame("status IN ('active', 'pending')", $sql);
    }

    public function testRenderInParameterized(): void
    {
        $collector = new BindCollector();
        $sql = $this->renderer->render(Condition::in('status', ['active', 'pending']), null, $collector);

        self::assertSame('status IN (:w_0_status,:w_1_status)', $sql);
        self::assertCount(2, $collector->getBinds());
    }

    public function testRenderNullAndNotNull(): void
    {
        self::assertSame('deleted_at IS NULL', $this->renderer->render(Condition::isNull('deleted_at')));
        self::assertSame('deleted_at IS NOT NULL', $this->renderer->render(Condition::notNull('deleted_at')));
    }

    public function testRenderExpression(): void
    {
        $sql = $this->renderer->render(Condition::equals('user_id', new Expression('"users"."id"')));

        self::assertSame('user_id = "users"."id"', $sql);
    }

    public function testRenderConditionGroup(): void
    {
        $group = ConditionGroup::all(
            Condition::equals('type', 'admin'),
            Condition::greater('age', 18),
        );

        $sql = $this->renderer->render($group);

        self::assertSame("type = 'admin' AND age > 18", $sql);
    }

    public function testRenderSubqueryExists(): void
    {
        $sub = new SelectStatement('users', ['1']);
        $sub->addCondition(Condition::equals('id', 42));

        $collector = new BindCollector();
        $sql = $this->renderer->render(Condition::exists($sub), null, $collector);

        self::assertSame('EXISTS (SELECT 1 FROM "users" WHERE (id = :sq0_w_0_id))', $sql);
        self::assertCount(1, $collector->getBinds());
    }
}
