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

The fastest way to get started is using the scaffolding command:

```bash
php bin/console persistence:scaffold User --table=users
```

This will generate:
- `UserSchema` (Enum)
- `User` (Entity)
- `UserHydrator` (Mapper)
- `UserRepository` (Repository)

### Manual Setup

#### 1. Define Schema
```php
use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Contract\IHasIndexes;
use Modular\Persistence\Schema\Definition\ColumnDefinition;
use Modular\Persistence\Schema\Definition\Index;

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
            self::Name => ColumnDefinition::text($this, nullable: true),
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

## 🏢 Multi-Tenancy

Modular Persistence supports multi-tenancy via Postgres schemas (namespaces).

```php
// 1. Setup Provider & Factory
$nsProvider = new RuntimeNamespaceProvider();
$factory = new GenericStatementFactory($nsProvider);

// 2. Inject into Repository (usually done via DI container)
$repo = new UserRepository($db, $hydrator, $factory);

// 3. Switch Context
$nsProvider->setNamespace('tenant_123');
$repo->findBy(); // Executes: SELECT * FROM "tenant_123"."users"
```

## 🛠️ Console Commands

- `persistence:scaffold` - Generate all files for a domain entity
- `persistence:make-schema` - Generate a Schema Enum
- `persistence:make-entity` - Generate an Entity class
- `persistence:make-hydrator` - Generate a Hydrator
- `persistence:make-repository` - Generate a Repository
- `persistence:generate-schema` - Generate SQL migration from Schema Enums
