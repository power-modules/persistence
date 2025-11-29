<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema;

use InvalidArgumentException;
use Modular\Persistence\Schema\Index;
use PHPUnit\Framework\TestCase;

final class IndexTest extends TestCase
{
    public function testItShouldGenerateIndexName(): void
    {
        $columns = ['id', 'name'];
        $index = new Index($columns, null, false);
        $expectedIndexName = 'idx_296856443';

        self::assertSame($expectedIndexName, $index->makeName(''));
    }

    public function testItShouldValidateColumnList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column list cannot be empty.');

        new Index([], '', true);
    }

    public function testItShouldValidateIndexName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An index name cannot be empty.');

        new Index(['col_a'], '', true);
    }
}
