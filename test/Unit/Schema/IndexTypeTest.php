<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema;

use Modular\Persistence\Schema\Definition\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexType::class)]
final class IndexTypeTest extends TestCase
{
    /**
     * @return array<string, array{IndexType, string}>
     */
    public static function indexTypeProvider(): array
    {
        return [
            'Btree' => [IndexType::Btree, 'BTREE'],
            'Hash' => [IndexType::Hash, 'HASH'],
            'Gin' => [IndexType::Gin, 'GIN'],
            'Gist' => [IndexType::Gist, 'GiST'],
            'SpGist' => [IndexType::SpGist, 'SP-GiST'],
            'Brin' => [IndexType::Brin, 'BRIN'],
        ];
    }

    #[DataProvider('indexTypeProvider')]
    public function testItShouldReturnCorrectDbType(IndexType $type, string $expectedDbType): void
    {
        self::assertSame($expectedDbType, $type->getDbType());
    }

    public function testItShouldHaveAllExpectedCases(): void
    {
        self::assertCount(6, IndexType::cases());
    }
}
