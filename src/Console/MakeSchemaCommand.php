<?php

declare(strict_types=1);

namespace Modular\Persistence\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'persistence:make-schema',
    description: 'Creates a new Schema Enum.',
)]
final class MakeSchemaCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the Schema Enum (e.g. UserSchema)')
            ->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'The database table name')
            ->addOption('folder', null, InputOption::VALUE_OPTIONAL, 'The folder to generate the file in', 'src/Schema')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'The namespace of the Schema Enum');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $table = $input->getOption('table') ?? strtolower(str_replace('Schema', '', $name)) . 's';
        $folder = $input->getOption('folder');

        if (!str_ends_with($name, 'Schema')) {
            $name .= 'Schema';
        }

        // Ensure directory exists
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $namespace = $input->getOption('namespace') ?? $this->guessNamespace($folder);
        $filePath = rtrim($folder, '/') . '/' . $name . '.php';

        if (file_exists($filePath)) {
            $io->error(sprintf('File "%s" already exists.', $filePath));

            return Command::FAILURE;
        }

        $content = $this->generateContent($namespace, $name, $table);
        file_put_contents($filePath, $content);

        $io->success(sprintf('Schema "%s" created at "%s".', $name, $filePath));

        return Command::SUCCESS;
    }

    private function guessNamespace(string $path): string
    {
        // Simple heuristic: replace / with \, remove src/, capitalize
        // This is a naive implementation and might need adjustment for specific project structures
        $path = trim($path, '/');
        if (str_starts_with($path, 'src/')) {
            $path = substr($path, 4);
        }

        $parts = array_map('ucfirst', explode('/', $path));
        // Assuming App is the root namespace for src, but it could be anything.
        // For now, let's try to detect composer.json autoload

        return 'App\\' . implode('\\', $parts);
    }

    private function generateContent(string $namespace, string $className, string $tableName): string
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Definition\ColumnDefinition;

enum {$className}: string implements ISchema
{
    case Id = 'id';
    // case CreatedAt = 'created_at';

    public static function getTableName(): string
    {
        return '{$tableName}';
    }

    public static function getPrimaryKey(): array
    {
        return [self::Id->value];
    }

    public function getColumnDefinition(): ColumnDefinition
    {
        return match (\$this) {
            self::Id => ColumnDefinition::uuid(\$this),
            // self::CreatedAt => ColumnDefinition::timestamptz(\$this),
        };
    }
}
PHP;

        return $content . "\n";
    }
}
