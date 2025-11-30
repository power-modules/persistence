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
    name: 'persistence:make-entity',
    description: 'Creates a new Entity class from a Schema.',
)]
final class MakeEntityCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the Entity (e.g. User)')
            ->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'The Schema Enum class (FQCN)')
            ->addOption('folder', null, InputOption::VALUE_OPTIONAL, 'The folder to generate the file in', 'src/Domain')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'The namespace for the class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $schemaClass = $input->getOption('schema');
        $folder = $input->getOption('folder');
        $namespace = $input->getOption('namespace');

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

        $properties = [];
        if ($schemaClass && enum_exists($schemaClass)) {
            $properties = $this->extractPropertiesFromSchema($schemaClass);
        } elseif ($schemaClass) {
            $io->warning(sprintf('Schema class "%s" not found. Generating empty entity.', $schemaClass));
        }

        $content = $this->generateContent($namespace, $name, $properties);
        file_put_contents($filePath, $content);

        $io->success(sprintf('Entity "%s" created at "%s".', $name, $filePath));

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
     * @return array<string, string>
     */
    private function extractPropertiesFromSchema(string $schemaClass): array
    {
        if (!is_subclass_of($schemaClass, \UnitEnum::class)) {
            return [];
        }

        $properties = [];
        /** @var ReflectionEnum<ISchema&\UnitEnum> $reflection */
        $reflection = new ReflectionEnum($schemaClass);

        foreach ($reflection->getCases() as $case) {
            $columnName = $case->getName();
            // Convention: Enum case is PascalCase, property is camelCase
            $propertyName = lcfirst($columnName);

            /** @var ISchema $enumCase */
            $enumCase = $case->getValue();
            $definition = $enumCase->getColumnDefinition();

            $phpType = match ($definition->columnType) {
                ColumnType::Int, ColumnType::Bigint, ColumnType::SmallInt, ColumnType::Tinyint => 'int',
                ColumnType::Decimal => 'float',
                ColumnType::Jsonb => 'array',
                ColumnType::Timestamp, ColumnType::TimestampTz, ColumnType::Date => '\DateTimeImmutable',
                default => 'string',
            };

            if ($definition->nullable) {
                $phpType = '?' . $phpType;
            }

            $properties[$propertyName] = $phpType;
        }

        return $properties;
    }

    /**
     * @param array<string, string> $properties
     */
    private function generateContent(string $namespace, string $className, array $properties): string
    {
        $propsCode = '';
        $constructorParams = [];
        $assignments = [];

        foreach ($properties as $name => $type) {
            $propsCode .= "    public readonly {$type} \${$name};\n";
            $constructorParams[] = "{$type} \${$name}";
            $assignments[] = "\$this->{$name} = \${$name};";
        }

        if (empty($properties)) {
            $propsCode = "    public readonly ?int \$id;\n";
            $constructorParams[] = "?int \$id = null";
            $assignments[] = "\$this->id = \$id;";
        }

        $constructorArgs = implode(",\n        ", $constructorParams);
        $assignmentCode = implode("\n        ", $assignments);

        // Using constructor property promotion would be cleaner
        $promotedParams = [];
        foreach ($properties as $name => $type) {
            $promotedParams[] = "public readonly {$type} \${$name}";
        }
        if (empty($properties)) {
            $promotedParams[] = "public readonly ?string \$id = null";
        }
        $promotedArgs = implode(",\n        ", $promotedParams);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

class {$className}
{
    public function __construct(
        {$promotedArgs},
    ) {
    }
}
PHP;

        return $content . "\n";
    }
}
