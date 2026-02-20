<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Repository\Statement;

use InvalidArgumentException;
use Modular\Persistence\Repository\Statement\Contract\Bind;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bind::class)]
final class BindTest extends TestCase
{
    public function testCreateWithString(): void
    {
        $bind = Bind::create('col', ':p', 'value');
        self::assertSame('value', $bind->value);
        self::assertSame(PDO::PARAM_STR, $bind->type);
    }

    public function testCreateWithInteger(): void
    {
        $bind = Bind::create('col', ':p', 42);
        self::assertSame(42, $bind->value);
        self::assertSame(PDO::PARAM_INT, $bind->type);
    }

    public function testCreateWithBoolean(): void
    {
        $bind = Bind::create('col', ':p', true);
        self::assertTrue($bind->value);
        self::assertSame(PDO::PARAM_BOOL, $bind->type);
    }

    public function testCreateWithNull(): void
    {
        $bind = Bind::create('col', ':p', null);
        self::assertNull($bind->value);
        self::assertSame(PDO::PARAM_NULL, $bind->type);
    }

    public function testConstructorRejectsNonScalarValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bind value should be of scalar type');

        new Bind('col', ':p', ['array'], PDO::PARAM_STR);
    }

    public function testJsonFactoryEncodesArray(): void
    {
        $bind = Bind::json('metadata', ':kw', ['status' => 'active']);

        self::assertSame('{"status":"active"}', $bind->value);
        self::assertSame(PDO::PARAM_STR, $bind->type);
        self::assertSame('metadata', $bind->column);
        self::assertSame(':kw', $bind->name);
    }

    public function testJsonFactoryEncodesNestedArray(): void
    {
        $bind = Bind::json('col', ':p', ['keywords' => ['php', 'jsonb']]);

        self::assertSame('{"keywords":["php","jsonb"]}', $bind->value);
        self::assertSame(PDO::PARAM_STR, $bind->type);
    }

    public function testJsonFactoryPassesThroughString(): void
    {
        $json = '{"status":"active"}';
        $bind = Bind::json('metadata', ':kw', $json);

        self::assertSame($json, $bind->value);
        self::assertSame(PDO::PARAM_STR, $bind->type);
    }

    public function testJsonFactoryEmptyArray(): void
    {
        $bind = Bind::json('col', ':p', []);

        self::assertSame('[]', $bind->value);
        self::assertSame(PDO::PARAM_STR, $bind->type);
    }
}
