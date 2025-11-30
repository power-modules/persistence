# Modular Persistence

A type-safe, enum-driven persistence layer for PHP 8.3+ applications built on the Modular Framework. This library provides a repository pattern with fluent query building, schema management, and PostgreSQL-optimized database operations.

## Features

- 🔒 **Type Safety**: Enum-based column references prevent SQL injection and typos
- 🏗️ **Repository Pattern**: Generic repositories with built-in CRUD operations
- 🔍 **Fluent Query Building**: Chainable query builders for complex operations
- 📊 **Schema Management**: Code-first schema definitions with DDL generation
- 🗄️ **PostgreSQL Optimized**: Full support for PostgreSQL features like `ILIKE`
- ⚡ **Transaction Support**: Built-in transaction management
- 🧪 **Testable**: Integration and unit testing patterns included

## Installation

```bash
composer require modular/persistence
```

## Quick Start

### 1. Define Your Schema

```php
<?php

use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\{ColumnDefinition, ColumnType};

enum UserSchema implements ISchema
{
    case Id;
    case Email;
    case Name;
    case CreatedAt;

    public static function getTableName(): string
    {
        return 'users';
    }

    public static function getPrimaryKey(): array
    {
        return ['id'];
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match ($this) {
            self::Id => ColumnDefinition::autoincrement($this),
            self::Email => ColumnDefinition::varchar($this, 255, nullable: false),
            self::Name => ColumnDefinition::varchar($this, 100),
            self::CreatedAt => ColumnDefinition::timestamp($this, nullable: false),
        };
    }
}
```

### 2. Create a Hydrator

The Hydrator is responsible for mapping database rows to objects and extracting data for persistence. It is also the source of truth for entity identity.

```php
<?php

use Modular\Persistence\Schema\Contract\IHydrator;
use Modular\Persistence\Schema\TStandardIdentity;

class UserHydrator implements IHydrator
{
    use TStandardIdentity; // Provides default getId() and getIdFieldName() implementation

    public function hydrate(array $row): User
    {
        return new User(
            (int)$row['id'],
            $row['email'],
            $row['name'],
            new DateTimeImmutable($row['created_at'])
        );
    }

    public function dehydrate(object $entity): array
    {
        return [
            'email' => $entity->email,
            'name' => $entity->name,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
```

### 3. Create a Repository

```php
<?php

use Modular\Persistence\Repository\AbstractGenericRepository;

class UserRepository extends AbstractGenericRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOne(
            Condition::equals(UserSchema::Email, $email)
        );
    }

    public function findActiveUsers(): array
    {
        return $this->getMany(
            Condition::notNull(UserSchema::Email)
        );
    }
}
```

### 4. Configure the Module

```php
<?php
// config/modular_persistence.php

use Modular\Persistence\Config\{Config, Setting};
use PDO;

return Config::create()
    ->set(Setting::Dsn, 'postgresql://user:pass@localhost/mydb')
    ->set(Setting::Username, 'myuser')
    ->set(Setting::Password, 'mypass')
    ->set(Setting::Options, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
```

### 5. Register and Use

```php
<?php

use Modular\Framework\App\ModularAppFactory;
use Modular\Persistence\PersistenceModule;
use Modular\Persistence\Repository\Statement\Factory\GenericStatementFactory;

$app = ModularAppFactory::create();
$app->registerModules([PersistenceModule::class]);

// Standard setup
$userRepository = new UserRepository(
    $app->get(IDatabase::class),
    new UserHydrator(),
    new GenericStatementFactory() // Optional, defaults to GenericStatementFactory
);

// Create a new user
$user = new User(null, 'john@example.com', 'John Doe', new DateTimeImmutable());
$userRepository->save($user); // Automatically handles insert vs update

// Find users
$user = $userRepository->findByEmail('john@example.com');
$activeUsers = $userRepository->findActiveUsers();
```

## Multi-Tenancy Support

The library supports multi-tenancy via Postgres schemas (namespaces). You can switch the schema context at runtime without re-instantiating your repositories.

```php
use Modular\Persistence\Repository\Statement\Provider\RuntimeNamespaceProvider;
use Modular\Persistence\Repository\Statement\Factory\GenericStatementFactory;

// 1. Setup the provider and factory
$namespaceProvider = new RuntimeNamespaceProvider();
$statementFactory = new GenericStatementFactory($namespaceProvider);

// 2. Inject into your repository
$userRepository = new UserRepository($database, $hydrator, $statementFactory);

// 3. Switch context at runtime (e.g., in a middleware)
$namespaceProvider->setNamespace('tenant_123');

// 4. Execute queries - they will target "tenant_123"."users"
$users = $userRepository->findAll();
```

## Key Concepts

### Enum-Based Column Safety

All database operations use `BackedEnum` values for column references:

```php
// ✅ Type-safe column reference
Condition::equals(UserSchema::Email, 'user@example.com')

// ❌ Runtime error prone
Condition::equals('email', 'user@example.com') // Not allowed
```

### Operator Validation

The `Operator` enum validates value types at construction:

```php
// ✅ Valid combinations
Condition::equals(UserSchema::Name, 'John')           // Scalar value
Condition::in(UserSchema::Status, ['active', 'pending']) // Array value
Condition::isNull(UserSchema::DeletedAt)              // Null value

// ❌ Invalid - throws InvalidArgumentException
Condition::equals(UserSchema::Name, null)             // Wrong type
Condition::in(UserSchema::Status, 'active')           // Should be array
```

### Fluent Query Building

Build complex queries with method chaining:

```php
$selectStatement = $this->getSelectStatement()
    ->addCondition(Condition::notNull(UserSchema::Email))
    ->addCondition(Condition::greater(UserSchema::CreatedAt, '2024-01-01'))
    ->addOrder(UserSchema::Name->value, 'ASC')
    ->setLimit(50)
    ->setStart(100);

$users = $this->select($selectStatement);
```

### Schema Generation

Generate database tables from your enums:

```php
use Modular\Persistence\Schema\Adapter\PostgresSchemaQueryGenerator;

$generator = new PostgresSchemaQueryGenerator();

foreach ($generator->generate(UserSchema::class) as $query) {
    $database->exec($query);
}
// Creates: CREATE TABLE "users" ("id" BIGSERIAL PRIMARY KEY, ...)
```

### Foreign Key Relationships

Define foreign key relationships in your schema enums:

```php
use Modular\Persistence\Schema\Contract\{ISchema, IHasForeignKeys};
use Modular\Persistence\Schema\Definition\ForeignKey;

enum OrderSchema implements ISchema, IHasForeignKeys
{
    case Id;
    case UserId;
    case ProductId;
    
    // ... other methods ...
    
    public static function getForeignKeys(): array
    {
        return [
            // Standard foreign key to same schema
            new ForeignKey(self::UserId->value, 'users', 'id'),
            
            // Foreign key with specific schema name (PostgreSQL)
            new ForeignKey(self::ProductId->value, 'products', 'id', 'catalog'),
            
            // Using the static make method with enums
            ForeignKey::make(self::UserId, 'users', UserSchema::Id, 'auth'),
        ];
    }
}
```

This generates SQL like:
```sql
FOREIGN KEY ("user_id") REFERENCES "users"("id")
FOREIGN KEY ("product_id") REFERENCES "catalog"."products"("id")
```

### Transaction Management

Built-in transaction support in repositories:

```php
$this->beginTransaction();
try {
    $this->insertOne($user);
    $this->insertOne($profile);
    $this->commit();
} catch (Exception $e) {
    $this->rollback();
    throw $e;
}
```

## Testing

### Run Tests

```bash
# All tests
make test

# Code style check
make codestyle

# Static analysis
make phpstan
```

### Integration Testing

Use real database connections:

```php
class UserRepositoryTest extends TestCase
{
    public function testUserCreation(): void
    {
        $app = ModularAppFactory::forAppRoot(__DIR__);
        $app->registerModules([PersistenceModule::class]);
        
        $repository = new UserRepository(
            $app->get(IDatabase::class),
            new UserHydrator()
        );
        
        // Test with real database...
    }
}
```

## Advanced Features

### Custom Operators

PostgreSQL-specific operators are supported:

```php
// Case-insensitive search (PostgreSQL ILIKE)
Condition::ilike(UserSchema::Name, '%john%')
```

### Join Operations

Build complex queries with joins:

```php
$selectStatement = $this->getSelectStatement()
    ->addJoin(new Join(JoinType::Inner, 'profiles', UserSchema::Id, ProfileSchema::UserId))
    ->addCondition(Condition::equals(ProfileSchema::Status, 'active'));

$results = $this->select($selectStatement);
```

### Bulk Operations

Efficient bulk inserts with model objects:

```php
$users = [
    new User('user1@example.com', 'User 1'),
    new User('user2@example.com', 'User 2'),
    // ... more user models
];

$rowsInserted = $this->insertMany($users);
```

## Requirements

- PHP 8.3+
- PostgreSQL (primary support)
- Modular Framework ^1.0

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass: `make test`
5. Check code style: `make codestyle`
6. Run static analysis: `make phpstan`
7. Submit a pull request

## License

MIT License. See LICENSE file for details.
