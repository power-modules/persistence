<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema;

use Modular\Persistence\Schema\Definition\ColumnDefinition;
use Modular\Persistence\Test\Unit\Repository\Fixture\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnDefinition::class)]
final class ColumnDefinitionTest extends TestCase
{
    public function testWithName(): void
    {
        $columnDefinition = ColumnDefinition::varchar(Schema::Id, 255);
        $columnDefinitionWithNewName = $columnDefinition->withName(Schema::Name);

        self::assertNotSame($columnDefinition, $columnDefinitionWithNewName);
        self::assertSame($columnDefinition->name, 'id');
        self::assertSame($columnDefinitionWithNewName->name, 'name');
    }
}
