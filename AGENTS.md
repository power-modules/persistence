# AGENTS.md

## Persistence Components

### `Config`
- Purpose: Provides typed accessors for persistence module settings from `config/modular_persistence.php`.
- Interface: `create()`, `getConfigFilename()`, `getDsn()`, `getUsername()`, `getPassword()`, `getSearchPath()`, `getOptions()`.
- Dependencies: `PowerModuleConfig`, `Setting`, `PDO`.

### `DatabaseConnectionFactory`
- Purpose: Creates configured PDO-backed database adapters and applies Postgres-only connection settings such as `search_path`.
- Interface: `make()`, `makeDatabase()`, `makePdo()`, `makePostgresDatabase()`.
- Dependencies: `Config`, `PDO`, `Database`, `PostgresDatabase`.

### `PostgresDatabase`
- Purpose: Extends the generic database adapter with Postgres search path management, namespace switching, and notification support.
- Interface: `getSearchPath()`, `setSearchPath()`, `useNamespace()`, `pgsqlGetNotify()`.
- Dependencies: `Database`, `IPostgresDatabase`, `PDO`.
