# Modular Persistence AI Instructions

## Architecture Overview
- **Core**: Type-safe persistence layer for PHP 8.4+ built on `power-modules/framework`.
- **Database**: `IDatabase` acts as a facade composed of `IQueryExecutor` and `ITransactionManager`.
- **Repository Pattern**: `AbstractGenericRepository` provides generic CRUD, decoupled from SQL generation via `IStatementFactory`.
- **Multi-Tenancy**: Native support for dynamic Postgres schemas (namespaces) via `INamespaceProvider`.
- **Schema**: Defined via Enums implementing `ISchema`.

## Key Components
### Database Layer (`src/Database`)
- **IDatabase**: The primary dependency for repositories. Combines execution and transaction capabilities.
- **IQueryExecutor**: Handles `query`, `execute`, `prepare`.
- **ITransactionManager**: Handles `beginTransaction`, `commit`, `rollback`.
- **PostgresDatabase**: Concrete implementation handling driver-specifics.

### Repository Layer (`src/Repository`)
- **AbstractGenericRepository**: Base class. Implements `save` (upsert), `delete`, `find`, etc.
- **IStatementFactory** (`src/Repository/Statement/Contract`): Interface for creating SQL statements.
- **GenericStatementFactory** (`src/Repository/Statement/Factory`): Standard factory. Supports `INamespaceProvider`.
- **RuntimeNamespaceProvider** (`src/Repository/Statement/Provider`): Allows runtime switching of database schemas (e.g., for multi-tenant apps).

### Schema & Hydration (`src/Schema`)
- **ISchema** (`src/Schema/Contract`): Interface for Schema Enums.
- **Definitions** (`src/Schema/Definition`): Contains `ColumnDefinition`, `ColumnType`, `ForeignKey`, `Index`.
- **IHydrator** (`src/Schema/Contract`): **Crucial Component**.
  - Maps rows to objects (`hydrate`).
  - Extracts data for persistence (`dehydrate`).
  - **Source of Truth for Identity**: Must implement `getId(object $entity)` and `getIdFieldName()`.
  - **TStandardIdentity**: Trait providing default `getId` and `getIdFieldName` implementation.

## Design Decisions & Pitfalls
1.  **Hydrator Responsibility**: The Hydrator is the sole authority on Entity Identity. The Repository does not use reflection to find IDs; it asks the Hydrator.
    - *Pitfall*: Forgetting to implement `getId` or `getIdFieldName` correctly will break `save()` and `updateOne()`. Use `TStandardIdentity` for standard cases.
2.  **Repository `save()` Logic**: The `save` method is an "upsert". It checks if the entity has an ID (via Hydrator). If yes -> `updateOne`, if no -> `insert`.
3.  **Statement Factories**: Never instantiate `SelectStatement` or `UpdateStatement` directly in Repositories. Always use `$this->statementFactory`. This ensures multi-tenancy support works.
4.  **Database Composition**: We moved away from a monolithic `Database` class. It now delegates to `QueryExecutor` and `TransactionManager`. This improves testability and adheres to ISP.
5.  **Multi-Tenancy**: We use Postgres Schemas (Namespaces). This is handled by injecting a `RuntimeNamespaceProvider` into the `GenericStatementFactory`.
    - *Decision*: We do not pass the namespace to every repository method. It is a cross-cutting concern handled by the Factory/Provider.

## Developer Workflows
- **Testing**: Run `make test` (PHPUnit).
- **Static Analysis**: Run `make phpstan` (PHPStan).
- **Code Style**: Run `make codestyle` (PHP-CS-Fixer).
- **Mandatory Workflow**: Before finishing your work, you MUST run/fix/repeat `make phpstan && make test` until errors are gone.

## Examples

### Multi-Tenant Setup
```php
// 1. Setup Provider & Factory
$nsProvider = new RuntimeNamespaceProvider();
$factory = new GenericStatementFactory($nsProvider);

// 2. Inject into Repository
$repo = new UserRepository($db, $hydrator, $factory);

// 3. Runtime Context Switch
$nsProvider->setNamespace('tenant_123');
$repo->findAll(); // Executes: SELECT * FROM "tenant_123"."users"
```

### Hydrator Implementation
```php
class UserHydrator implements IHydrator {
    use TStandardIdentity; // Handles getId() and getIdFieldName()

    public function hydrate(array $row): User {
        return new User((int)$row['id'], $row['email']);
    }
    
    public function dehydrate(mixed $entity): array {
        return ['email' => $entity->email];
    }
}
```

### Repository Usage
```php
class UserRepository extends AbstractGenericRepository {
    // Custom methods use the factory
    public function findActive(): array {
        $stmt = $this->statementFactory->createSelectStatement($this->tableName);
        $stmt->addCondition(Condition::equals(UserSchema::Status, 'active'));
        // ...
    }
}

// Usage
$user = new User(null, 'test@example.com');
$repo->save($user); // Inserts because getId() returns null

$user->email = 'changed@example.com';
$repo->save($user); // Updates because getId() returns ID
```
