<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema;

use InvalidArgumentException;
use Modular\Persistence\Schema\Definition\Index;
use Modular\Persistence\Schema\Definition\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Index::class)]
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

    public function testItShouldDefaultToBtreeType(): void
    {
        $index = new Index(['col_a'], null, false);

        self::assertSame(IndexType::Btree, $index->type);
    }

    public function testItShouldAcceptCustomIndexType(): void
    {
        $index = new Index(['col_a'], null, false, IndexType::Gin);

        self::assertSame(IndexType::Gin, $index->type);
    }

    public function testMakeNameShouldDifferForDifferentTypes(): void
    {
        $columns = ['col_a', 'col_b'];
        $btreeIndex = new Index($columns, null, false, IndexType::Btree);
        $ginIndex = new Index($columns, null, false, IndexType::Gin);

        self::assertNotSame($btreeIndex->makeName('table'), $ginIndex->makeName('table'));
    }

    public function testMakeNameShouldBeConsistentForBtreeDefault(): void
    {
        $columns = ['id', 'name'];
        $indexWithExplicitBtree = new Index($columns, null, false, IndexType::Btree);
        $indexWithDefault = new Index($columns, null, false);

        self::assertSame($indexWithExplicitBtree->makeName(''), $indexWithDefault->makeName(''));
    }

    public function testMakeNameShouldIncludeTableName(): void
    {
        $index = new Index(['col_a'], null, false, IndexType::Hash);
        $name = $index->makeName('users');

        self::assertStringStartsWith('idx_users_', $name);
    }

    public function testMakeNameShouldDifferForEachNonBtreeType(): void
    {
        $columns = ['col_a'];
        $names = [];

        foreach (IndexType::cases() as $type) {
            $index = new Index($columns, null, false, $type);
            $names[$type->name] = $index->makeName('table');
        }

        self::assertCount(count(IndexType::cases()), array_unique($names));
    }

    // --- Expression index tests (Solution C) ---

    public function testExpressionFactoryCreatesExpressionIndex(): void
    {
        $index = Index::expression("(\"metadata\"->'keywords')", IndexType::Gin);

        self::assertTrue($index->isExpression);
        self::assertSame(["(\"metadata\"->'keywords')"], $index->columns);
        self::assertSame(IndexType::Gin, $index->type);
        self::assertFalse($index->isUnique);
        self::assertNull($index->name);
    }

    public function testExpressionFactoryWithCustomName(): void
    {
        $index = Index::expression(
            "(\"metadata\"->'keywords')",
            IndexType::Gin,
            name: 'idx_metadata_keywords',
        );

        self::assertSame('idx_metadata_keywords', $index->name);
    }

    public function testExpressionFactoryWithUnique(): void
    {
        $index = Index::expression(
            "(lower(\"email\"))",
            IndexType::Btree,
            unique: true,
        );

        self::assertTrue($index->isUnique);
        self::assertTrue($index->isExpression);
    }

    public function testMakeFactoryDefaultsIsExpressionFalse(): void
    {
        $index = new Index(['col_a'], null, false, IndexType::Gin);

        self::assertFalse($index->isExpression);
    }

    public function testExpressionMakeNameIsConsistent(): void
    {
        $index = Index::expression("(\"data\"->'key')", IndexType::Gin);
        $name1 = $index->makeName('articles');
        $name2 = $index->makeName('articles');

        self::assertSame($name1, $name2);
        self::assertStringStartsWith('idx_articles_', $name1);
    }
}
