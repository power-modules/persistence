# Modular Persistence

[![CI](https://github.com/power-modules/persistence/actions/workflows/php.yml/badge.svg)](https://github.com/power-modules/persistence/actions/workflows/php.yml)
[![Packagist Version](https://img.shields.io/packagist/v/power-modules/persistence)](https://packagist.org/packages/power-modules/persistence)
[![PHP Version](https://img.shields.io/packagist/php-v/power-modules/persistence)](https://packagist.org/packages/power-modules/persistence)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-blue)](#)

A **type-safe, multi-tenant persistence layer** for PHP 8.4+ built on the Modular Framework. It provides a robust Repository pattern implementation with native support for Postgres schemas and strict type safety.

> **💡 Robust:** Built for complex applications requiring strict data integrity, multi-tenancy, and clear separation of concerns.

## ✨ Why Modular Persistence?

- **🔒 Type-Safe Schemas**: Define database schemas using PHP Enums
- **🏢 Multi-Tenancy Native**: Built-in support for dynamic Postgres schemas (namespaces)
- **📦 Repository Pattern**: Generic CRUD repositories with decoupled SQL generation
- **🔄 Explicit Hydration**: Full control over object-relational mapping without magic
- **🛠️ Scaffolding**: CLI commands to generate your entire persistence layer
- **⚡ Performance**: Lightweight wrapper around PDO with optimized query generation

## 🚀 Installation

```bash
composer require power-modules/persistence
```

## ⚙️ Configuration

Register the module in your `ModularAppBuilder` and provide configuration in `config/modular_persistence.php`:

```php
// config/modular_persistence.php
<?php

declare(strict_types=1);

use Modular\Persistence\Config\Config;
use Modular\Persistence\Config\Setting;

return Config::create()
    ->set(Setting::Dsn, $_ENV['DB_DSN'] ?? 'pgsql:host=localhost;port=5432;dbname=myapp')
    ->set(Setting::Username, $_ENV['DB_USERNAME'] ?? 'postgres')
    ->set(Setting::Password, $_ENV['DB_PASSWORD'] ?? 'secret')
    ->set(Setting::Options, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ])
;
```

## 🏗️ Quick Start

#### 1. Define Schema
```php
use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Contract\IHasIndexes;
use Modular\Persistence\Schema\Definition\ColumnDefinition;
use Modular\Persistence\Schema\Definition\Index;
use Modular\Persistence\Schema\Definition\IndexType;

enum UserSchema: string implements ISchema, IHasIndexes
{
    case Id = 'id';
    case Email = 'email';
    case Name = 'name';

    public static function getTableName(): string
    {
        return 'users';
    }
    
    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::uuid($this)->primaryKey(),
            self::Email => ColumnDefinition::text($this),
            self::Name => ColumnDefinition::text($this)->nullable(),
        };
    }

    public static function getIndexes(): array
    {
        return [
            Index::make([self::Email], unique: true),
        ];
    }
}
```

#### 2. Create Entity & Hydrator
```php
readonly class User
{
    public function __construct(
        public string $id,
        public string $email,
        public ?string $name,
    ) {}
}

class UserHydrator implements IHydrator
{
    use TStandardIdentity;

    public function hydrate(array $data): User
    {
        return new User(
            Uuid::fromString($data[UserSchema::Id->value]),
            $data[UserSchema::Email->value],
            $data[UserSchema::Name->value],
        );
    }

    public function dehydrate(mixed $entity): array
    {
        return [
            UserSchema::Id->value => $entity->id,
            UserSchema::Email->value => $entity->email,
            UserSchema::Name->value => $entity->name,
        ];
    }
}
```

#### 3. Use Repository
```php
class UserRepository extends AbstractGenericRepository
{
    protected function getTableName(): string
    {
        return UserSchema::getTableName();
    }
}

// Usage
$repo = $app->get(UserRepository::class);
$user = new User(Uuid::uuid7()->toString(), 'test@example.com', 'Test User');
$repo->upsert($user);
```

## 📦 Repository API

`AbstractGenericRepository` implements the `IRepository<TModel>` interface, providing a complete CRUD contract out of the box. **Type-hint `IRepository<TModel>` in consumers** (services, controllers) — not `AbstractGenericRepository` — to enable decorator patterns (e.g., caching repos) and testability:

```php
use Modular\Persistence\Repository\Contract\IRepository;

class UserService
{
    /** @param IRepository<User> $users */
    public function __construct(
        private readonly IRepository $users,
    ) {}
}
```

All repository methods:

```php
// Create
$repo->insert($user);                      // Insert a single entity
$repo->insertAll([$user1, $user2, ...]);    // Bulk insert (auto-chunked, auto-transaction)
$repo->insertAll($entities, chunkSize: 50); // Custom chunk size (default: 100)
$repo->save($user);                        // @deprecated — use upsert() instead (2 queries)
$repo->upsert($user);                      // Insert or update in a single query (ON CONFLICT)

// Read
$repo->find($id);                          // Find by primary key (or null)
$repo->findOrFail($id);                    // Find by primary key (or throw EntityNotFoundException)
$repo->findBy([                            // Find all matching conditions
    Condition::equals(UserSchema::Email, 'test@example.com'),
]);
$repo->findBy(limit: 10, offset: 20);      // Paginated results
$repo->findOneBy([                         // Find first match or null
    Condition::equals(UserSchema::Name, 'John'),
]);
$repo->findOneByOrFail([...]);             // Find first match or throw EntityNotFoundException
$repo->count();                            // Count all rows
$repo->exists([...]);                      // Check existence (bool)

// Update
$repo->update($user);                      // Update entity by ID
$repo->updateBy(                           // Update arbitrary columns by conditions
    ['name' => 'New Name'],
    [Condition::equals(UserSchema::Email, 'old@example.com')],
);

// Delete
$repo->delete($id);                        // Delete by primary key
$repo->deleteBy([...]);                    // Delete by conditions
```

## 🔍 Conditions

Type-safe query conditions using schema enum cases:

```php
use Modular\Persistence\Repository\Condition;
use Modular\Persistence\Repository\ConditionXor;

// Comparison
Condition::equals(UserSchema::Email, 'test@example.com');
Condition::notEquals(UserSchema::Status, 'inactive');
Condition::greater(UserSchema::Age, 18);
Condition::greaterEquals(UserSchema::Age, 18);
Condition::less(UserSchema::Age, 65);
Condition::lessEquals(UserSchema::Age, 65);

// Sets
Condition::in(UserSchema::Role, ['admin', 'editor']);
Condition::notIn(UserSchema::Status, ['banned', 'suspended']);

// Nullability
Condition::isNull(UserSchema::DeletedAt);
Condition::notNull(UserSchema::Email);

// Pattern matching
Condition::like(UserSchema::Name, 'John');       // LIKE '%John%'
Condition::ilike(UserSchema::Name, 'john');      // ILIKE '%john%' (Postgres, case-insensitive)

// Subqueries
Condition::exists('SELECT 1 FROM orders WHERE orders.user_id = users.id');

// OR conditions
new Condition(UserSchema::Role, Operator::Equals, 'admin', ConditionXor::Or);
```

### JSONB Conditions

PostgreSQL JSONB operators are supported natively:

```php
// Containment: metadata @> '{"status":"active"}'::jsonb
Condition::jsonContains(ArticleSchema::Metadata, '{"status":"active"}');

// Reverse containment: metadata <@ '{"status":"active","lang":"en"}'::jsonb
Condition::jsonContainedBy(ArticleSchema::Metadata, '{"status":"active","lang":"en"}');

// Key existence: jsonb_exists(metadata, 'status')
Condition::jsonHasKey(ArticleSchema::Metadata, 'status');

// Any key existence: jsonb_exists_any(metadata, array['status','lang'])
Condition::jsonHasAnyKey(ArticleSchema::Metadata, ['status', 'lang']);

// All keys existence: jsonb_exists_all(metadata, array['status','lang'])
Condition::jsonHasAllKeys(ArticleSchema::Metadata, ['status', 'lang']);

// JSON path expression with standard operators (column expression is NOT quoted)
Condition::jsonPath('"metadata"->>\'status\'', Operator::Equals, 'active');
Condition::jsonPath('"metadata"->>\'title\'', Operator::Ilike, 'search term');
```

### Raw SQL Conditions

For complex expressions that cannot be represented via `Condition` (e.g. custom casts, functions, or advanced JSONB paths), use `addRawCondition()` on any statement:

```php
use Modular\Persistence\Repository\Statement\Contract\Bind;

$select = $this->getSelectStatement();
$select->addCondition(Condition::ilike(ArticleSchema::Title, 'search'));
$select->addRawCondition(
    "\"metadata\"->'keywords' @> :kw_filter::jsonb",
    [Bind::json('keywords', ':kw_filter', ['php', 'jsonb'])],
);
$select->setLimit(20);
$select->setStart(0);

// Works seamlessly with both select and count
$data = $this->select($select);
$total = $this->count([], $select);
```

`addRawCondition()` is available on `SelectStatement`, `UpdateStatement`, and `DeleteStatement`. Raw conditions are AND-joined with standard conditions. `Bind::json()` auto-encodes arrays to JSON strings.
```

## 🔗 Joins

```php
use Modular\Persistence\Repository\Join;
use Modular\Persistence\Repository\JoinType;

$select = $this->statementFactory->createSelectStatement($this->getTableName());
$select->addJoin(
    new Join(
        JoinType::Left,
        'orders',
        UserSchema::Id,          // Local key (accepts BackedEnum)
        OrderSchema::UserId,     // Foreign key (accepts BackedEnum)
    ),
);
$select->addCondition(Condition::notNull(OrderSchema::Id));
$select->addOrder('created_at', 'DESC');
$select->setLimit(10);
$select->setStart(0);
```

Supported join types: `Inner`, `Left`, `Outer`.

### Type-cast joins (`localKeyType`)

When the local key column stores values as `text` but the foreign key expects a specific type (e.g. `uuid`, `integer`), pass the `localKeyType` parameter:

```php
$select->addJoin(
    new Join(
        JoinType::Inner,
        'departments',
        'dept_uuid',        // Local key (text column)
        'id',               // Foreign key (uuid column)
        localKeyType: 'uuid',
    ),
);
// Produces: INNER JOIN "departments" ON "departments"."id" = NULLIF("employees"."dept_uuid", '')::uuid
```

The generated SQL uses `NULLIF(col, '')::type` to safely handle empty strings — an empty string is converted to `NULL` before casting, preventing PostgreSQL errors like `invalid input syntax for type uuid: ""`.

## 🔄 Transactions

The `IDatabase` interface provides full transaction control:

```php
$db->beginTransaction();

try {
    $userRepo->insert($user);
    $orderRepo->insert($order);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

> **Note:** `insertAll()` automatically wraps in a transaction if one isn't already active.

## ⚡ Upsert & Conflict Handling

The repository provides a convenient `upsert()` method for single-query insert-or-update:

```php
// Single-query upsert via ON CONFLICT ... DO UPDATE
$repo->upsert($user);
// Always returns 1 (whether inserted or updated)
```

For more control, use the statement factory directly:

```php
// Ignore duplicates (ON CONFLICT DO NOTHING)
$insert = $this->statementFactory->createInsertStatement('users', ['id', 'email']);
$insert->prepareBinds(['id' => $id, 'email' => $email]);
$insert->ignoreDuplicates();

// Custom upsert (ON CONFLICT ... DO UPDATE)
$insert = $this->statementFactory->createInsertStatement('users', ['id', 'email', 'name']);
$insert->prepareBinds(['id' => $id, 'email' => $email, 'name' => $name]);
$insert->onConflictUpdate(
    conflictColumns: ['email'],
    updateColumns: ['name'],
);
// Generates: INSERT INTO "users" ... ON CONFLICT ("email") DO UPDATE SET "name" = EXCLUDED."name"
```

## 🏢 Multi-Tenancy

Modular Persistence supports multi-tenancy via Postgres schemas (namespaces) using a **Decorator Pattern** on the database connection. This ensures `search_path` is correctly set for every query, allowing for clean SQL generation and correct Foreign Key resolution.

```php
// 1. Setup Database with Decorator
$rawDb = new PostgresDatabase($pdo);
$nsProvider = new RuntimeNamespaceProvider();

// Decorate the database to handle automatic context switching
$db = new NamespaceAwarePostgresDatabase($rawDb, $nsProvider);

// 2. Setup Factory (No namespace provider needed here for dynamic tenancy)
$factory = new GenericStatementFactory();

// 3. Inject into Repository
$repo = new UserRepository($db, $hydrator, $factory);

// 4. Switch Context
$nsProvider->setNamespace('tenant_123');
$repo->findBy(); 
// Internally executes: 
// SET search_path TO "tenant_123"; 
// SELECT * FROM "users";
```

## 🗂️ Schema Features

### Column Types

`ColumnDefinition` provides static factories for all supported types:

```php
ColumnDefinition::uuid($col)->primaryKey();        // UUID primary key
ColumnDefinition::autoincrement($col)->primaryKey(); // BIGINT auto-increment
ColumnDefinition::text($col);                       // TEXT
ColumnDefinition::varchar($col, 255);                // VARCHAR(255)
ColumnDefinition::int($col);                         // INTEGER
ColumnDefinition::bigint($col);                      // BIGINT
ColumnDefinition::smallint($col);                    // SMALLINT
ColumnDefinition::tinyint($col);                     // TINYINT
ColumnDefinition::decimal($col, 10, 2);              // DECIMAL(10,2)
ColumnDefinition::date($col);                        // DATE
ColumnDefinition::timestamp($col);                   // TIMESTAMP
ColumnDefinition::timestamptz($col);                 // TIMESTAMPTZ
ColumnDefinition::jsonb($col);                       // JSONB
ColumnDefinition::mediumblob($col);                  // MEDIUMBLOB

// Modifiers (returns new immutable instance)
ColumnDefinition::text($col)->nullable()->default('N/A');
```

### Foreign Keys

Schema enums can declare foreign key constraints by implementing `IHasForeignKeys`:

```php
use Modular\Persistence\Schema\Contract\IHasForeignKeys;
use Modular\Persistence\Schema\Definition\ForeignKey;

enum OrderSchema: string implements ISchema, IHasForeignKeys
{
    case Id = 'id';
    case UserId = 'user_id';

    // ...

    public static function getForeignKeys(): array
    {
        return [
            ForeignKey::make(
                localColumnName: self::UserId,
                foreignTableName: 'users',
                foreignColumnName: UserSchema::Id,
                foreignSchemaName: 'public', // Optional: cross-schema FK
            ),
        ];
    }
}
```

### SQL Generation

The `PostgresSchemaQueryGenerator` generates DDL from schema enums, including `CREATE TABLE`, `ALTER TABLE ADD COLUMN`, `ALTER TABLE RENAME COLUMN`, and index creation. Use the CLI command or the generator directly:

```bash
php bin/console persistence:generate-schema App\\Schema\\UserSchema
```

### Expression Indexes

For JSONB path indexes or functional indexes, use `Index::expression()` to avoid automatic identifier quoting:

```php
public static function getIndexes(): array
{
    return [
        // Full-column GIN index (column quoted as identifier)
        Index::make([self::Metadata], type: IndexType::Gin),
        
        // Expression-based GIN index (expression NOT quoted)
        Index::expression("(\"metadata\"->'keywords')", IndexType::Gin),
        
        // Functional index
        Index::expression("(lower(\"email\"))", IndexType::Btree, unique: true),
    ];
}
// Generates:
// CREATE INDEX ... USING GIN ("metadata");
// CREATE INDEX ... USING GIN (("metadata"->'keywords'));
// CREATE UNIQUE INDEX ... ((lower("email")));
```

## 🛠️ Console Commands

| Command | Description |
|---|---|
| `persistence:make-schema` | Generate a Schema Enum |
| `persistence:make-entity` | Generate an Entity class from a Schema |
| `persistence:make-hydrator` | Generate a Hydrator from a Schema + Entity |
| `persistence:make-repository` | Generate a Repository |
| `persistence:generate-schema` | Generate SQL migration files from Schema Enums |

## 📊 Query Logging

`LoggingQueryExecutor` is an opt-in decorator that wraps `IQueryExecutor` and logs every query via PSR-3 `LoggerInterface`:

```php
use Modular\Persistence\Database\LoggingQueryExecutor;

$loggingExecutor = new LoggingQueryExecutor($queryExecutor, $logger);
// Logs: query string, elapsed_ms, affected_rows (for exec)
```

## 🧪 Development & Testing

### Prerequisites

- PHP 8.4+
- Docker & Docker Compose

### Local Setup

```bash
# Start PostgreSQL (port 15432)
make docker-up

# Run all tests (unit + integration)
make test

# Run unit tests only (no DB required)
make test-unit

# Run integration tests only (requires PostgreSQL)
make test-integration

# Static analysis (PHPStan level 8)
make phpstan

# Code style check
make codestyle

# Stop PostgreSQL
make docker-down
```

### Test Architecture

- **Unit tests** (`tests/Unit/`): Fast, no database — mock `IDatabase`/`IQueryExecutor` to verify SQL generation and bind parameters
- **Integration tests** (`tests/Integration/`): Run against a real PostgreSQL 17 instance, testing the full stack from repository through PDO
- **Test isolation**: Integration tests use transaction-based isolation (`BEGIN` in setUp / `ROLLBACK` in tearDown) for speed. Tests that exercise transactions or DDL manage their own cleanup.
- **Fixtures**: Shared test entities, schemas, hydrators, and repositories live in `tests/Unit/Fixture/` (mocked DB) and `tests/Integration/Fixture/` (real DB). Integration support classes (`PostgresTestCase`, `ConnectionHelper`) live in `tests/Integration/Support/`.
- **Namespace**: `Modular\Persistence\Tests\` (PSR-4 → `tests/`)

### CI

The GitHub Actions workflow automatically provisions a PostgreSQL service container and runs the full suite on every push/PR.
