<?php

declare(strict_types=1);

namespace Modular\Persistence\Tests\Integration\Repository;

use Modular\Persistence\Database\NamespaceAwarePostgresDatabase;
use Modular\Persistence\Repository\AbstractGenericRepository;
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\Statement\Factory\GenericStatementFactory;
use Modular\Persistence\Repository\Statement\Provider\RuntimeNamespaceProvider;
use Modular\Persistence\Tests\Integration\Fixture\Note;
use Modular\Persistence\Tests\Integration\Fixture\NoteHydrator;
use Modular\Persistence\Tests\Integration\Fixture\NoteRepository;
use Modular\Persistence\Tests\Integration\Support\ConnectionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for namespace-qualified repository operations.
 *
 * Tests GenericStatementFactory with INamespaceProvider correctly qualifying
 * table names as "namespace"."table", and the full stack (repository → statement
 * factory → database) with namespace-aware PostgreSQL schemas.
 *
 * Extends TestCase directly for explicit schema lifecycle management.
 */
#[CoversClass(AbstractGenericRepository::class)]
#[CoversClass(GenericStatementFactory::class)]
final class NamespacedRepositoryTest extends TestCase
{
    use ConnectionHelper;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        try {
            $db = static::connect();
        } catch (\PDOException $e) {
            static::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        $db->exec('DROP SCHEMA IF EXISTS ns_alpha CASCADE');
        $db->exec('DROP SCHEMA IF EXISTS ns_beta CASCADE');
        $db->exec('CREATE SCHEMA ns_alpha');
        $db->exec('CREATE SCHEMA ns_beta');
        $db->exec('CREATE TABLE ns_alpha.notes ("id" VARCHAR(36) PRIMARY KEY, "title" VARCHAR(255) NOT NULL)');
        $db->exec('CREATE TABLE ns_beta.notes ("id" VARCHAR(36) PRIMARY KEY, "title" VARCHAR(255) NOT NULL)');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $db = static::connect();
        $db->exec('DELETE FROM ns_alpha.notes');
        $db->exec('DELETE FROM ns_beta.notes');
    }

    private function createRepositoryForNamespace(string $namespace): NoteRepository
    {
        $db = static::connect();
        $factory = new GenericStatementFactory($namespace);

        return new NoteRepository($db, new NoteHydrator(), $factory);
    }

    // ── Static namespace via GenericStatementFactory ──────────────────

    public function testInsertAndFindWithStaticNamespace(): void
    {
        $repoAlpha = $this->createRepositoryForNamespace('ns_alpha');
        $repoBeta = $this->createRepositoryForNamespace('ns_beta');

        $noteA = new Note(Uuid::uuid7()->toString(), 'Alpha Note');
        $repoAlpha->insert($noteA);

        $noteB = new Note(Uuid::uuid7()->toString(), 'Beta Note');
        $repoBeta->insert($noteB);

        $alphaResults = $repoAlpha->findBy();
        self::assertCount(1, $alphaResults);
        self::assertSame('Alpha Note', $alphaResults[0]->title);

        $betaResults = $repoBeta->findBy();
        self::assertCount(1, $betaResults);
        self::assertSame('Beta Note', $betaResults[0]->title);
    }

    public function testUpsertWithNamespace(): void
    {
        $repo = $this->createRepositoryForNamespace('ns_alpha');

        $note = new Note(Uuid::uuid7()->toString(), 'Original');
        $repo->upsert($note);

        $updated = new Note($note->id, 'Updated');
        $repo->upsert($updated);

        self::assertSame(1, $repo->count());
        $results = $repo->findBy();
        self::assertSame('Updated', $results[0]->title);
    }

    public function testDeleteWithNamespace(): void
    {
        $repo = $this->createRepositoryForNamespace('ns_beta');

        $note = new Note(Uuid::uuid7()->toString(), 'Deletable');
        $repo->insert($note);
        self::assertSame(1, $repo->count());

        $repo->delete($note->id);
        self::assertSame(0, $repo->count());
    }

    public function testUpdateByWithNamespace(): void
    {
        $repo = $this->createRepositoryForNamespace('ns_alpha');

        $note = new Note(Uuid::uuid7()->toString(), 'Before');
        $repo->insert($note);

        $repo->updateBy(['title' => 'After'], [Condition::equals('id', $note->id)]);

        $found = $repo->find($note->id);
        self::assertNotNull($found);
        self::assertSame('After', $found->title);
    }

    public function testNamespaceIsolation(): void
    {
        $repoAlpha = $this->createRepositoryForNamespace('ns_alpha');
        $repoBeta = $this->createRepositoryForNamespace('ns_beta');

        $repoAlpha->insert(new Note(Uuid::uuid7()->toString(), 'A1'));
        $repoAlpha->insert(new Note(Uuid::uuid7()->toString(), 'A2'));
        $repoBeta->insert(new Note(Uuid::uuid7()->toString(), 'B1'));

        self::assertSame(2, $repoAlpha->count());
        self::assertSame(1, $repoBeta->count());

        $repoAlpha->deleteBy([Condition::equals('title', 'A1')]);
        self::assertSame(1, $repoAlpha->count());
        self::assertSame(1, $repoBeta->count());
    }

    // ── RuntimeNamespaceProvider + NamespaceAwarePostgresDatabase ────

    public function testRuntimeNamespaceProviderWithRepository(): void
    {
        $db = static::connect();
        $provider = new RuntimeNamespaceProvider();
        $nsDb = new NamespaceAwarePostgresDatabase($db, $provider);

        $repo = new NoteRepository($nsDb, new NoteHydrator());

        $provider->setNamespace('ns_alpha');
        $repo->insert(new Note(Uuid::uuid7()->toString(), 'Runtime Alpha'));

        $provider->setNamespace('ns_beta');
        $repo->insert(new Note(Uuid::uuid7()->toString(), 'Runtime Beta'));

        // Verify isolation via search_path
        $provider->setNamespace('ns_alpha');
        self::assertSame(1, $repo->count());
        $alphaResults = $repo->findBy();
        self::assertSame('Runtime Alpha', $alphaResults[0]->title);

        $provider->setNamespace('ns_beta');
        self::assertSame(1, $repo->count());
        $betaResults = $repo->findBy();
        self::assertSame('Runtime Beta', $betaResults[0]->title);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            $db = static::connect();
            $db->exec('DROP SCHEMA IF EXISTS ns_alpha CASCADE');
            $db->exec('DROP SCHEMA IF EXISTS ns_beta CASCADE');
        } catch (\PDOException) {
            // Ignore
        }

        static::resetConnection();
        parent::tearDownAfterClass();
    }
}
