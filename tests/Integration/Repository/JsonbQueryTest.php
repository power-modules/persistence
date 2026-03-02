<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Repository;

use DateTimeImmutable;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Operator;
use Modular\Persistence\Tests\Integration\Fixture\Product;
use Modular\Persistence\Tests\Integration\Fixture\ProductHydrator;
use Modular\Persistence\Tests\Integration\Fixture\ProductRepository;
use Modular\Persistence\Tests\Integration\Fixture\ProductSchema;
use Modular\Persistence\Tests\Integration\Support\PostgresTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for PostgreSQL JSONB query operators.
 *
 * Tests @>, <@, ?, ?|, ?& operators and jsonPath conditions against real PostgreSQL.
 */
#[CoversClass(AbstractGenericRepository::class)]
final class JsonbQueryTest extends PostgresTestCase
{
    protected static function getSchemas(): array
    {
        return [ProductSchema::Id];
    }

    private function getRepository(): ProductRepository
    {
        return new ProductRepository(
            static::getConnection(),
            new ProductHydrator(),
        );
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @param array<string>|null $tags
     */
    private function createProduct(string $name, ?array $metadata = null, ?array $tags = null): Product
    {
        return new Product(
            Uuid::uuid7()->toString(),
            $name,
            $metadata,
            $tags,
            new DateTimeImmutable(),
        );
    }

    private function seedProducts(): void
    {
        $repo = $this->getRepository();
        $repo->insertAll([
            $this->createProduct('Widget', ['color' => 'red', 'size' => 'large', 'status' => 'active'], ['electronics', 'sale']),
            $this->createProduct('Gadget', ['color' => 'blue', 'size' => 'small', 'status' => 'active'], ['electronics']),
            $this->createProduct('Doohickey', ['color' => 'red', 'size' => 'medium', 'status' => 'inactive'], ['home', 'sale']),
            $this->createProduct('Thingamajig', null, null),
        ]);
    }

    // ── @> (JsonContains) ────────────────────────────────────────────

    public function testJsonContains(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $results = $repo->findBy([
            Condition::jsonContains(ProductSchema::Metadata, (string) json_encode(['color' => 'red'])),
        ]);

        self::assertCount(2, $results);
        $names = array_map(fn (Product $p) => $p->name, $results);
        self::assertContains('Widget', $names);
        self::assertContains('Doohickey', $names);
    }

    public function testJsonContainsMultipleKeys(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $results = $repo->findBy([
            Condition::jsonContains(ProductSchema::Metadata, (string) json_encode(['color' => 'red', 'size' => 'large'])),
        ]);

        self::assertCount(1, $results);
        self::assertSame('Widget', $results[0]->name);
    }

    // ── <@ (JsonContainedBy) ─────────────────────────────────────────

    public function testJsonContainedBy(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $superset = (string) json_encode(['color' => 'blue', 'size' => 'small', 'status' => 'active', 'extra' => 'field']);
        $results = $repo->findBy([
            Condition::jsonContainedBy(ProductSchema::Metadata, $superset),
        ]);

        self::assertCount(1, $results);
        self::assertSame('Gadget', $results[0]->name);
    }

    // ── ? (JsonHasKey) ───────────────────────────────────────────────

    public function testJsonHasKey(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $results = $repo->findBy([
            Condition::jsonHasKey(ProductSchema::Metadata, 'color'),
        ]);

        self::assertCount(3, $results);
    }

    public function testJsonHasKeyMissing(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $results = $repo->findBy([
            Condition::jsonHasKey(ProductSchema::Metadata, 'weight'),
        ]);

        self::assertCount(0, $results);
    }

    // ── ?| (JsonHasAnyKey) ───────────────────────────────────────────

    public function testJsonHasAnyKey(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $results = $repo->findBy([
            Condition::jsonHasAnyKey(ProductSchema::Metadata, ['color', 'weight']),
        ]);

        self::assertCount(3, $results);
    }

    // ── ?& (JsonHasAllKeys) ──────────────────────────────────────────

    public function testJsonHasAllKeys(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $results = $repo->findBy([
            Condition::jsonHasAllKeys(ProductSchema::Metadata, ['color', 'size', 'status']),
        ]);

        self::assertCount(3, $results);
    }

    public function testJsonHasAllKeysMissingSome(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $results = $repo->findBy([
            Condition::jsonHasAllKeys(ProductSchema::Metadata, ['color', 'weight']),
        ]);

        self::assertCount(0, $results);
    }

    // ── jsonPath (->>) ───────────────────────────────────────────────

    public function testJsonPathExtraction(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $results = $repo->findBy([
            Condition::jsonPath('"metadata"->>\'status\'', Operator::Equals, 'active'),
        ]);

        self::assertCount(2, $results);
        $names = array_map(fn (Product $p) => $p->name, $results);
        self::assertContains('Widget', $names);
        self::assertContains('Gadget', $names);
    }

    // ── NULL handling ────────────────────────────────────────────────

    public function testJsonbNullHandling(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        self::assertCount(1, $repo->findBy([Condition::isNull(ProductSchema::Metadata)]));
        self::assertCount(3, $repo->findBy([Condition::notNull(ProductSchema::Metadata)]));
    }

    // ── Upsert with JSONB ────────────────────────────────────────────

    public function testUpsertWithJsonb(): void
    {
        $repo = $this->getRepository();

        $product = $this->createProduct('UpsertProduct', ['version' => 1], ['tag1']);
        $repo->upsert($product);

        $found = $repo->find($product->id);
        self::assertNotNull($found);
        self::assertSame(['version' => 1], $found->metadata);
        self::assertSame(['tag1'], $found->tags);

        // Upsert with updated JSONB data
        $updated = new Product($product->id, 'UpsertProduct', ['version' => 2, 'new_key' => 'val'], ['tag1', 'tag2'], $product->createdAt);
        $repo->upsert($updated);

        $found = $repo->find($product->id);
        self::assertNotNull($found);
        self::assertEquals(['version' => 2, 'new_key' => 'val'], $found->metadata);
        self::assertSame(['tag1', 'tag2'], $found->tags);
        self::assertSame(1, $repo->count());
    }

    // ── Count / Delete with JSONB conditions ─────────────────────────

    public function testCountWithJsonbCondition(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $count = $repo->count([
            Condition::jsonContains(ProductSchema::Metadata, (string) json_encode(['status' => 'active'])),
        ]);

        self::assertSame(2, $count);
    }

    public function testDeleteByJsonbCondition(): void
    {
        $this->seedProducts();
        $repo = $this->getRepository();

        $deleted = $repo->deleteBy([
            Condition::jsonContains(ProductSchema::Metadata, (string) json_encode(['status' => 'inactive'])),
        ]);

        self::assertSame(1, $deleted);
        self::assertSame(3, $repo->count());
    }
}
