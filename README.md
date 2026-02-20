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
$repo->save($user);
```

## 📦 Repository API

`AbstractGenericRepository` provides a complete CRUD interface out of the box:

```php
// Create
$repo->insert($user);                      // Insert a single entity
$repo->insertAll([$user1, $user2, ...]);    // Bulk insert (auto-chunked, auto-transaction)
$repo->save($user);                        // Upsert: inserts if new, updates if exists

// Read
$repo->find($id);                          // Find by primary key
$repo->findBy([                            // Find all matching conditions
    Condition::equals(UserSchema::Email, 'test@example.com'),
]);
$repo->findOneBy([                         // Find first match or null
    Condition::equals(UserSchema::Name, 'John'),
]);
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

For advanced insert behavior, use the statement factory directly:

```php
// Ignore duplicates (ON CONFLICT DO NOTHING)
$insert = $this->statementFactory->createInsertStatement('users', ['id', 'email']);
$insert->prepareBinds(['id' => $id, 'email' => $email]);
$insert->ignoreDuplicates();

// Upsert (ON CONFLICT ... DO UPDATE)
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

## 🛠️ Console Commands

| Command | Description |
|---|---|
| `persistence:make-schema` | Generate a Schema Enum |
| `persistence:make-entity` | Generate an Entity class from a Schema |
| `persistence:make-hydrator` | Generate a Hydrator from a Schema + Entity |
| `persistence:make-repository` | Generate a Repository |
| `persistence:generate-schema` | Generate SQL migration files from Schema Enums |
