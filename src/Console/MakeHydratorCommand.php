<?php

declare(strict_types=1);

namespace Modular\Persistence\Console;

use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnType;
use ReflectionEnum;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'persistence:make-hydrator',
    description: 'Creates a new Hydrator class.',
)]
final class MakeHydratorCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the Hydrator (e.g. UserHydrator)')
            ->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'The Schema Enum class (FQCN)')
            ->addOption('entity', 'e', InputOption::VALUE_REQUIRED, 'The Entity class (FQCN)')
            ->addOption('folder', null, InputOption::VALUE_OPTIONAL, 'The folder to generate the file in', 'src/Hydrator')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'The namespace for the class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $schemaClass = $input->getOption('schema');
        $entityClass = $input->getOption('entity');
        $folder = $input->getOption('folder');
        $namespace = $input->getOption('namespace');

        if (!str_ends_with($name, 'Hydrator')) {
            $name .= 'Hydrator';
        }

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        if (!$namespace) {
            $namespace = $this->guessNamespace($folder);
        }

        $filePath = rtrim($folder, '/') . '/' . $name . '.php';

        if (file_exists($filePath)) {
            $io->error(sprintf('File "%s" already exists.', $filePath));

            return Command::FAILURE;
        }

        $mapping = [];
        if ($schemaClass && enum_exists($schemaClass)) {
            $mapping = $this->getMapping($schemaClass);
        } elseif ($schemaClass) {
            $io->warning(sprintf('Schema class "%s" not found. Generating skeleton.', $schemaClass));
        }

        $content = $this->generateContent($namespace, $name, $entityClass, $mapping, $schemaClass);
        file_put_contents($filePath, $content);

        $io->success(sprintf('Hydrator "%s" created at "%s".', $name, $filePath));

        return Command::SUCCESS;
    }

    private function guessNamespace(string $path): string
    {
        $path = trim($path, '/');
        if (str_starts_with($path, 'src/')) {
            $path = substr($path, 4);
        }
        $parts = array_map('ucfirst', explode('/', $path));

        return 'App\\' . implode('\\', $parts);
    }

    /**
     * @param class-string<\UnitEnum> $schemaClass
     * @return array<int, array{property: string, column: string, cast: string, dateFormat: string, nullable: bool, enumCase: string}>
     */
    private function getMapping(string $schemaClass): array
    {
        $mapping = [];
        $reflection = new ReflectionEnum($schemaClass);

        foreach ($reflection->getCases() as $case) {
            $caseName = $case->getName();
            $propertyName = lcfirst($caseName);
            $dbColumn = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $caseName)); // Pascal to snake_case

            /** @var ISchema $enumCase */
            $enumCase = $case->getValue();
            $definition = $enumCase->getColumnDefinition();

            $dateFormat = 'Y-m-d H:i:s';
            $cast = match ($definition->columnType) {
                ColumnType::Int, ColumnType::Bigint, ColumnType::SmallInt, ColumnType::Tinyint => '(int)',
                ColumnType::Decimal => '(float)',
                ColumnType::Jsonb => 'json_decode',
                ColumnType::Timestamp => 'datetime',
                ColumnType::TimestampTz => 'datetime',
                ColumnType::Date => 'datetime',
                default => '',
            };

            if ($definition->columnType === ColumnType::TimestampTz) {
                $dateFormat = 'Y-m-d H:i:sP';
            } elseif ($definition->columnType === ColumnType::Date) {
                $dateFormat = 'Y-m-d';
            }

            $mapping[] = [
                'property' => $propertyName,
                'column' => $dbColumn,
                'cast' => $cast,
                'dateFormat' => $dateFormat,
                'nullable' => $definition->nullable,
                'enumCase' => $caseName,
            ];
        }

        return $mapping;
    }

    /**
     * @param array<int, array{property: string, column: string, cast: string, dateFormat: string, nullable: bool, enumCase: string}> $mapping
     */
    private function generateContent(string $namespace, string $className, ?string $entityClass, array $mapping, ?string $schemaClass = null): string
    {
        $entityImport = '';
        $entityShortName = 'object';
        $schemaImport = '';
        $schemaShortName = '';

        if ($entityClass) {
            if (str_contains($entityClass, '\\')) {
                $entityImport = "use {$entityClass};\n";
                $entityShortName = substr($entityClass, strrpos($entityClass, '\\') + 1);
            } else {
                $entityShortName = $entityClass;
                // Guess import
                $entityImport = "use App\\Domain\\{$entityClass};\n";
            }
        }

        if ($schemaClass) {
            if (str_contains($schemaClass, '\\')) {
                $schemaImport = "use {$schemaClass};\n";
                $schemaShortName = substr($schemaClass, strrpos($schemaClass, '\\') + 1);
            } else {
                $schemaShortName = $schemaClass;
                $schemaImport = "use App\\Schema\\{$schemaClass};\n";
            }
        }

        $hydrateBody = '';
        $dehydrateBody = '';

        if (empty($mapping)) {
            $hydrateBody = "        // TODO: Implement hydrate\n        return new {$entityShortName}();";
            $dehydrateBody = "        // TODO: Implement dehydrate\n        return [];";
        } else {
            // Generate hydrate
            $args = [];
            foreach ($mapping as $map) {
                $col = $map['column'];
                $cast = $map['cast'];
                $nullable = $map['nullable'];
                $enumCase = $map['enumCase'];

                // Use Schema::Case->value if schema is available, else string literal
                if ($schemaShortName && $enumCase) {
                    $access = "\$data[{$schemaShortName}::{$enumCase}->value]";
                } else {
                    $access = "\$data['{$col}']";
                }

                if ($cast === 'datetime') {
                    if ($nullable) {
                        $val = "isset({$access}) ? new \DateTimeImmutable({$access}) : null";
                    } else {
                        $val = "new \DateTimeImmutable({$access})";
                    }
                } elseif ($cast === 'json_decode') {
                    if ($nullable) {
                        $val = "isset({$access}) ? json_decode({$access}, true) : null";
                    } else {
                        $val = "json_decode({$access}, true)";
                    }
                } elseif ($cast) {
                    if ($nullable) {
                        $val = "isset({$access}) ? {$cast}{$access} : null";
                    } else {
                        $val = "{$cast}{$access}";
                    }
                } else {
                    $val = $access;
                }

                $args[] = $val;
            }
            $hydrateBody = "        return new {$entityShortName}(\n            " . implode(",\n            ", $args) . ",\n        );";

            // Generate dehydrate
            $fields = [];
            foreach ($mapping as $map) {
                $prop = $map['property'];
                $col = $map['column'];
                $cast = $map['cast'];
                $dateFormat = $map['dateFormat'];
                $enumCase = $map['enumCase'];

                if ($cast === 'datetime') {
                    if ($nullable) {
                        $val = "\$entity->{$prop}?->format('{$dateFormat}')";
                    } else {
                        $val = "\$entity->{$prop}->format('{$dateFormat}')";
                    }
                } elseif ($cast === 'json_decode') {
                    $val = "json_encode(\$entity->{$prop})";
                } else {
                    $val = "\$entity->{$prop}";
                }

                if ($schemaShortName && $enumCase) {
                    $key = "{$schemaShortName}::{$enumCase}->value";
                } else {
                    $key = "'{$col}'";
                }

                $fields[] = "{$key} => {$val}";
            }
            $dehydrateBody = "        return [\n            " . implode(",\n            ", $fields) . ",\n        ];";
        }

        $imports = [
            'use Modular\Persistence\Schema\Contract\IHydrator;',
            'use Modular\Persistence\Schema\TStandardIdentity;',
        ];

        if ($entityImport) {
            $imports[] = trim($entityImport);
        }
        if ($schemaImport) {
            $imports[] = trim($schemaImport);
        }

        sort($imports);
        $importsStr = implode("\n", $imports) . "\n";

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$importsStr}
/**
 * @implements IHydrator<{$entityShortName}>
 */
class {$className} implements IHydrator
{
    use TStandardIdentity;

    /**
     * @param array<string, mixed> \$data
     */
    public function hydrate(array \$data): {$entityShortName}
    {
{$hydrateBody}
    }

    /**
     * @param {$entityShortName} \$entity
     * @return array<string, mixed>
     */
    public function dehydrate(mixed \$entity): array
    {
{$dehydrateBody}
    }
}
PHP;

        return $content . "\n";
    }
}
