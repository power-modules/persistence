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
    name: 'persistence:make-repository',
    description: 'Creates a new Repository class.',
)]
final class MakeRepositoryCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the Repository (e.g. UserRepository)')
            ->addOption('hydrator', null, InputOption::VALUE_OPTIONAL, 'The Hydrator class name')
            ->addOption('schema', 's', InputOption::VALUE_OPTIONAL, 'The Schema Enum class (FQCN)')
            ->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'The database table name')
            ->addOption('folder', null, InputOption::VALUE_OPTIONAL, 'The folder to generate the file in', 'src/Repository')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'The namespace for the class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $hydratorClass = $input->getOption('hydrator');
        $schemaClass = $input->getOption('schema');
        $table = $input->getOption('table');
        $folder = $input->getOption('folder');
        $namespace = $input->getOption('namespace');

        if (!str_ends_with($name, 'Repository')) {
            $name .= 'Repository';
        }

        if (!$table && !$schemaClass) {
            // Guess table name: UserRepository -> users
            $baseName = substr($name, 0, -10); // Remove 'Repository'
            $table = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $baseName)) . 's';
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

        $content = $this->generateContent($namespace, $name, $hydratorClass, $schemaClass, $table);
        file_put_contents($filePath, $content);

        $io->success(sprintf('Repository "%s" created at "%s".', $name, $filePath));

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

    private function generateContent(string $namespace, string $className, ?string $hydratorClass, ?string $schemaClass, ?string $tableName): string
    {
        $hydratorImport = '';
        $hydratorType = 'IHydrator';
        $hydratorParam = 'IHydrator $hydrator';
        $entityName = 'object'; // Default if we can't guess
        $entityImport = '';
        $schemaImport = '';
        $tableNameCode = '';

        if ($schemaClass) {
            if (str_contains($schemaClass, '\\')) {
                $schemaImport = "use {$schemaClass};\n";
                $schemaShortName = substr($schemaClass, strrpos($schemaClass, '\\') + 1);
            } else {
                $schemaShortName = $schemaClass;
                $schemaImport = "use App\\Schema\\{$schemaClass};\n";
            }
            $tableNameCode = "return {$schemaShortName}::getTableName();";
        } else {
            $tableNameCode = "return '{$tableName}';";
        }

        if ($hydratorClass) {
            // Assuming Hydrator is in a sibling namespace or we need to import it
            // For simplicity, if it's a full class name, use it.
            // If it's just a name, assume it's in App\Hydrator or similar?
            // Let's just use the short name and assume the user will fix imports or provide FQCN
            if (str_contains($hydratorClass, '\\')) {
                $hydratorImport = "use {$hydratorClass};\n";
                $hydratorShortName = substr($hydratorClass, strrpos($hydratorClass, '\\') + 1);
            } else {
                $hydratorShortName = $hydratorClass;
                // Try to guess import if we can, but it's hard.
                // Let's assume it's in App\Hydrator
                $hydratorImport = "use App\\Hydrator\\{$hydratorClass};\n";
            }
            $hydratorType = $hydratorShortName;
            $hydratorParam = "{$hydratorShortName} \$hydrator";

            // Try to guess Entity name from Hydrator name (UserHydrator -> User)
            if (str_ends_with($hydratorShortName, 'Hydrator')) {
                $entityName = substr($hydratorShortName, 0, -8);
                $entityImport = "use App\\Domain\\{$entityName};\n";
            }
        } else {
            $hydratorImport = "use Modular\Persistence\Schema\Contract\IHydrator;\n";
            $hydratorType = 'IHydrator';
            $hydratorParam = 'IHydrator $hydrator';
        }

        $imports = [
            'use Modular\Persistence\Database\IDatabase;',
            'use Modular\Persistence\Repository\AbstractGenericRepository;',
        ];

        $imports[] = trim($hydratorImport);

        if ($entityImport) {
            $imports[] = trim($entityImport);
        }
        if ($schemaImport) {
            $imports[] = trim($schemaImport);
        }

        sort($imports);
        $importsStr = implode("\n", array_unique($imports)) . "\n";

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$importsStr}
/**
 * @extends AbstractGenericRepository<{$entityName}>
 */
class {$className} extends AbstractGenericRepository
{
    public function __construct(IDatabase \$database, {$hydratorType} \$hydrator)
    {
        parent::__construct(\$database, \$hydrator);
    }

    protected function getTableName(): string
    {
        {$tableNameCode}
    }
}
PHP;

        return $content . "\n";
    }
}
