<?php

declare(strict_types=1);

namespace Modular\Persistence\Schema\Adapter;

use Generator;
use Modular\Persistence\Schema\Contract\IHasForeignKeys;
use Modular\Persistence\Schema\Contract\IHasIndexes;
use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Contract\ISchemaQueryGenerator;
use Modular\Persistence\Schema\Definition\ColumnDefinition;
use Modular\Persistence\Schema\Definition\ColumnType;

final readonly class PostgresSchemaQueryGenerator implements ISchemaQueryGenerator
{
    public function generate(ISchema $schema, ?string $tableName = null): Generator
    {
        $tableName = $tableName ?? $schema::getTableName();
        $createTableQuery = $this->getCreateTableQuery($schema, $tableName);

        yield $createTableQuery;

        if ($schema instanceof IHasIndexes) {
            foreach ($schema->getIndexes() as $tableIndex) {
                $unique = $tableIndex->isUnique ? ' UNIQUE ' : ' ';

                $indexQuery = sprintf(
                    'CREATE%sINDEX "%s" ON "%s"("%s");',
                    $unique,
                    $tableIndex->name ?? $tableIndex->makeName($tableName),
                    $tableName,
                    implode('", "', $tableIndex->columns),
                );

                yield $indexQuery;
            }
        }
    }

    public function generateAlterAddColumn(ISchema $column): string
    {
        $columnDefinitionQuery = $this->getColumnDefinitionQuery($column->getColumnDefinition());

        return sprintf(
            'ALTER TABLE "%s" ADD COLUMN %s',
            $column->getTableName(),
            $columnDefinitionQuery,
        );
    }

    public function generateAlterChangeColumn(ISchema $column): string
    {
        $columnDefinitionQuery = $this->getColumnDefinitionQuery($column->getColumnDefinition());

        return sprintf(
            'ALTER TABLE "%s" CHANGE COLUMN "%s" %s',
            $column->getTableName(),
            $column->getColumnDefinition()->name,
            $columnDefinitionQuery,
        );
    }

    public function generateAlterRenameColumn(ISchema $column, string $oldName): string
    {
        return sprintf(
            'ALTER TABLE "%s" RENAME COLUMN "%s" TO "%s"',
            $column->getTableName(),
            $oldName,
            $column->getColumnDefinition()->name,
        );
    }

    private function getCreateTableQuery(ISchema $schema, string $tableName): string
    {
        $createTableQuery = sprintf(
            'CREATE TABLE "%s" (%s, PRIMARY KEY ("%s")',
            $tableName,
            implode(', ', $this->getColumnsDefinition($schema)),
            implode('", "', $schema::getPrimaryKey()),
        );

        if ($schema instanceof IHasForeignKeys) {
            foreach ($schema->getForeignKeys() as $foreignKeyConstraint) {
                $foreignTableReference = $foreignKeyConstraint->foreignSchemaName !== null
                    ? sprintf('"%s"."%s"', $foreignKeyConstraint->foreignSchemaName, $foreignKeyConstraint->foreignTableName)
                    : sprintf('"%s"', $foreignKeyConstraint->foreignTableName);

                $createTableQuery = sprintf(
                    '%s, FOREIGN KEY ("%s") REFERENCES %s("%s")',
                    $createTableQuery,
                    $foreignKeyConstraint->localColumnName,
                    $foreignTableReference,
                    $foreignKeyConstraint->foreignColumnName,
                );
            }
        }

        return sprintf('%s);', $createTableQuery);
    }

    /**
     * @return array<string>
     */
    private function getColumnsDefinition(ISchema $schema): array
    {
        return array_map(
            fn (ISchema $column): string => $this->getColumnDefinitionQuery($column->getColumnDefinition()),
            $schema::cases(),
        );
    }

    private function getColumnDefinitionQuery(ColumnDefinition $columnDefinition): string
    {
        $query = sprintf('"%s" %s', $columnDefinition->name, $columnDefinition->columnType->getDbType());

        $query = match ($columnDefinition->columnType) {
            ColumnType::Bigint => sprintf('%s', $query),
            ColumnType::Date => sprintf('%s', $query),
            ColumnType::Decimal => sprintf('%s(%d, %d)', $query, $columnDefinition->precision, $columnDefinition->scale),
            ColumnType::Int => sprintf('%s', $query),
            ColumnType::Mediumblob => sprintf('%s', $query),
            ColumnType::SmallInt => sprintf('%s', $query),
            ColumnType::Text => sprintf('%s', $query),
            ColumnType::Timestamp => sprintf('%s', $query),
            ColumnType::TimestampTz => sprintf('%s', $query),
            ColumnType::Tinyint => sprintf('%s', $query),
            ColumnType::Uuid => sprintf('%s', $query),
            ColumnType::Varchar => sprintf('%s(%d)', $query, $columnDefinition->precision),
            ColumnType::Jsonb => sprintf('%s', $query),
        };

        if ($columnDefinition->nullable === true) {
            $query = sprintf('%s NULL', $query);
        } else {
            $query = sprintf('%s NOT NULL', $query);
        }

        if ($columnDefinition->isAutoincrement) {
            $query = sprintf('%s AUTO_INCREMENT', $query);
        } else {
            if ($columnDefinition->default === null) {
                if ($columnDefinition->nullable === true) {
                    $query = sprintf('%s DEFAULT NULL', $query);
                }
            } else {
                if ($columnDefinition->default === 'CURRENT_TIMESTAMP') {
                    $query = sprintf('%s DEFAULT CURRENT_TIMESTAMP', $query);
                } else {
                    $query = sprintf('%s DEFAULT \'%s\'', $query, $columnDefinition->default);
                }
            }
        }

        return $query;
    }
}
