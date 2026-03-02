# Modular Persistence — AI Coding Instructions

## Architecture

Type-safe persistence layer for PHP 8.4+ (`Modular\Persistence\` namespace, PSR-4 → `src/`). Built on `power-modules/framework`.

**Database layer** (`src/Database/`): `IDatabase` extends `IQueryExecutor` + `ITransactionManager` (ISP). `Database` delegates to composed `QueryExecutor` and `TransactionManager` — never inherits from PDO. `PostgresDatabase` adds search_path management with namespace caching. `NamespaceAwarePostgresDatabase` is a **decorator** that auto-sets search_path before every query via `INamespaceProvider`.

**Repository layer** (`src/Repository/`): `IRepository<TModel>` defines the full repository contract. `AbstractGenericRepository<TModel>` implements it with generic CRUD (`upsert`, `find`, `findOrFail`, `findBy`, `findOneBy`, `findOneByOrFail`, `insert`, `insertAll`, `delete`, `deleteBy`, `count`, `exists`). `save()` is deprecated in favour of `upsert()`. Concrete repos only implement `getTableName(): string`. All SQL is built through `IStatementFactory` — never instantiate statements directly.

**Schema** (`src/Schema/`): Database schemas are **backed string enums** implementing `ISchema`. Each case is a column name, `getColumnDefinition()` returns immutable `ColumnDefinition` builders. Hydrators (`IHydrator<TModel>`) own entity↔row mapping AND entity identity (`getId`, `getIdFieldName`). Use `TStandardIdentity` trait for the common `id` field case.

## Critical Patterns

- **Hydrator is identity authority**: `upsert()` uses `hydrator->getId()` / `getIdFieldName()` for the conflict column. Implement `getId`/`getIdFieldName` correctly or use `TStandardIdentity`.
- **Enum-as-Schema**: Column references are type-safe enum cases (`UserSchema::Email`), not raw strings. `Condition::equals(UserSchema::Email, $val)`.
- **Statement factory only**: Never instantiate `SelectStatement`, `InsertStatement`, etc. directly. Use `$this->statementFactory->createSelectStatement(...)`. This ensures multi-tenancy namespace support works.
- **Upsert support**: `upsert()` on repository does single-query `ON CONFLICT ... DO UPDATE`. `InsertStatement` also supports `ignoreDuplicates()` (ON CONFLICT DO NOTHING) and `onConflictUpdate()` directly. `save()` is deprecated — prefer `upsert()`.
- **findOrFail / findOneByOrFail**: Throw `EntityNotFoundException` (extends `PersistenceException`) when entity not found instead of returning null.
- **PHP 8.4 features**: Asymmetric visibility (`public private(set)`), property hooks (auto-converting `BackedEnum` to string in `Condition`, `Join`), `readonly` classes, constructor promotion.
- **Immutable builders**: `ColumnDefinition::text($col)->nullable()->default('x')` returns new instances.
- **Multi-tenancy via search_path**: Use `NamespaceAwarePostgresDatabase` decorator + `RuntimeNamespaceProvider`. Statement factories accept `string|INamespaceProvider` for namespace-qualified table names.
- **Composition over traits**: `WhereClause` is composed into statements rather than mixed in via traits.
- **Type-hint `IRepository`**: Consumers (services, controllers) should depend on `IRepository<TModel>`, not `AbstractGenericRepository`. This enables decorator patterns (e.g., caching repos) and testability.
- **Generic templates**: Repositories and hydrators use `@template TModel of object` with `@extends` for PHPStan level 8 type safety.
- **Foreign keys**: Schema enums implement `IHasForeignKeys` for FK constraints, including cross-schema FKs via `foreignSchemaName`.
- **JSONB query support**: `Operator` has JSONB cases (`JsonContains`, `JsonContainedBy`, `JsonHasKey`, `JsonHasAnyKey`, `JsonHasAllKeys`). `Condition` provides matching factories (`jsonContains`, `jsonContainedBy`, `jsonHasKey`, `jsonHasAnyKey`, `jsonHasAllKeys`, `jsonPath`). `WhereClause` renders `::jsonb` / `::text[]` casts automatically. JSONB key-existence operators (`JsonHasKey`, `JsonHasAnyKey`, `JsonHasAllKeys`) are rendered as `jsonb_exists()`, `jsonb_exists_any()`, `jsonb_exists_all()` function calls instead of `?` / `?|` / `?&` operators to avoid PDO positional parameter conflicts.
- **Raw SQL conditions**: `WhereClause::addRaw(string $sql, array $binds)` and `addRawCondition()` on `SelectStatement`, `UpdateStatement`, `DeleteStatement` inject raw SQL fragments with bind values. Used for expressions that can't be represented via `Condition` (custom casts, complex JSONB paths, functions). Raw conditions are AND-joined with standard condition groups.
- **Bind::json() factory**: Creates binds for JSONB values. Arrays are auto-`json_encode()`d. Strings pass through as-is. No BC break — `Bind::create()` remains scalar-only.
- **Expression indexes**: `Index::expression(string $expression, IndexType $type)` creates an index where the column expression is stored verbatim (not quoted as an identifier). Used for JSONB path or functional indexes. `PostgresSchemaQueryGenerator` checks `$index->isExpression` to skip quoting.
- **Safe type-cast joins**: `Join` accepts optional `localKeyType` (e.g. `'uuid'`, `'integer'`). When set, `Join::toSql()` renders the local key as `NULLIF("table"."col", '')::type` — converting empty strings to `NULL` before casting to prevent PostgreSQL errors like `invalid input syntax for type uuid: ""`. Without `localKeyType`, the standard `"table"."col"` form is used. `SelectStatement::setupJoin()` delegates to `Join::toSql()` for SQL generation.

## Developer Workflow

```bash
make docker-up         # Start PostgreSQL 17 on port 15432
make docker-down       # Stop PostgreSQL
make test              # Run all tests (unit + integration)
make test-unit         # Run unit tests only (no DB required)
make test-integration  # Run integration tests only (requires PostgreSQL)
make codestyle         # PHP-CS-Fixer check (PSR-12 base, trailing commas, ordered imports, strict_types)
make phpstan           # PHPStan level 8 on src/ + tests/
```

**Before finishing any task**: Run `make phpstan && make test` and fix all errors. Repeat until clean.

After any codebase changes, review and update `README.md` and `.github/copilot-instructions.md` to keep documentation in sync with the code.

When running PHPUnit manually, always use `--display-all-issues` flag.

## Testing Conventions

- PHPUnit 12.5, attributes-based (`#[CoversClass(...)]`), `self::assert*` static style.
- `tests/Unit/` mirrors `src/` structure; `tests/Integration/` for real PostgreSQL tests.
- Namespace: `Modular\Persistence\Tests\` (PSR-4 → `tests/`).
- Unit test fixtures in `tests/Unit/Fixture/` — reference `EmployeeSchema`, `Employee`, `EmployeeHydrator`, `EmployeeRepository` for pattern examples.
- Integration test fixtures in `tests/Integration/Fixture/` — PostgreSQL-compatible schemas using VARCHAR/UUID primary keys (not autoincrement).
- Integration tests extend `PostgresTestCase` (`tests/Integration/Support/`) for transaction-based isolation (BEGIN in setUp, ROLLBACK in tearDown).
- Tests needing explicit transaction or DDL control (e.g., `MultiTenancyTest`, `TransactionTest`) extend `TestCase` directly and use the `ConnectionHelper` trait.
- Mock `IDatabase`/`IQueryExecutor` with `createMock()`/`createStub()` for isolated statement/bind verification in unit tests.
- Docker PostgreSQL on port 15432 locally; CI uses a GitHub Actions service container on port 5432.

## Code Style

PSR-12 base with: trailing commas in multiline constructs, ordered imports (alpha), `declare(strict_types=1)` everywhere, no unused imports, no empty phpdoc. See [.php-cs-fixer.php](.php-cs-fixer.php).

## Scaffolding Commands

```bash
php bin/console persistence:make-schema Name --table=table_name --folder=src/Schema
php bin/console persistence:make-entity SchemaClass --folder=src/Entity
php bin/console persistence:make-hydrator SchemaClass EntityClass --folder=src/Hydrator
php bin/console persistence:make-repository Name --folder=src/Repository
php bin/console persistence:generate-schema SchemaClass  # Outputs .sql alongside enum
```

## Key Reference Files

- Repository interface: [src/Repository/Contract/IRepository.php](src/Repository/Contract/IRepository.php)
- Repository base: [src/Repository/AbstractGenericRepository.php](src/Repository/AbstractGenericRepository.php)
- Query conditions: [src/Repository/Condition.php](src/Repository/Condition.php) (20 static factories: `equals`, `in`, `isNull`, `like`, `ilike`, `exists`, `jsonContains`, `jsonHasKey`, `jsonPath`, etc.)
- JSONB operators: [src/Repository/Operator.php](src/Repository/Operator.php) (standard + JSONB: `JsonContains`, `JsonContainedBy`, `JsonHasKey`, `JsonHasAnyKey`, `JsonHasAllKeys`)
- Raw conditions: `WhereClause::addRaw()` / `SelectStatement::addRawCondition()` / `UpdateStatement::addRawCondition()` / `DeleteStatement::addRawCondition()`
- Bind factory: [src/Repository/Statement/Contract/Bind.php](src/Repository/Statement/Contract/Bind.php) (`Bind::create()` for scalars, `Bind::json()` for JSONB values)
- Statement factory: [src/Repository/Statement/Factory/GenericStatementFactory.php](src/Repository/Statement/Factory/GenericStatementFactory.php)
- Column definitions: [src/Schema/Definition/ColumnDefinition.php](src/Schema/Definition/ColumnDefinition.php)
- Logging decorator: [src/Database/LoggingQueryExecutor.php](src/Database/LoggingQueryExecutor.php)
- Unit test fixtures: [tests/Unit/Fixture/](tests/Unit/Fixture/)
- Integration test fixtures: [tests/Integration/Fixture/](tests/Integration/Fixture/)
- Integration test base class: [tests/Integration/Support/PostgresTestCase.php](tests/Integration/Support/PostgresTestCase.php)
