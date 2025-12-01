<?php

declare(strict_types=1);

namespace Modular\Persistence\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'persistence:scaffold',
    description: 'Scaffolds the entire persistence stack (Schema, Entity, Hydrator, Repository).',
)]
final class ScaffoldCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The domain name (e.g. User)')
            ->addOption('folder', null, InputOption::VALUE_OPTIONAL, 'The base folder', 'src')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'The base namespace')
            ->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'The database table name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $folder = $input->getOption('folder');
        $baseNamespace = $input->getOption('namespace');
        $tableName = $input->getOption('table');
        $app = $this->getApplication();

        if (!$app) {
            $io->error('Application instance not found.');

            return Command::FAILURE;
        }

        if (!$baseNamespace) {
            $baseNamespace = $this->guessNamespace($folder);
        }

        // Define paths and namespaces
        $schemaFolder = rtrim($folder, '/') . '/Schema';
        $schemaNamespace = $baseNamespace . '\\Schema';
        $schemaClass = $schemaNamespace . '\\' . $name . 'Schema';

        $domainFolder = rtrim($folder, '/') . '/Domain';
        $domainNamespace = $baseNamespace . '\\Domain';
        $entityClass = $domainNamespace . '\\' . $name;

        $hydratorFolder = rtrim($folder, '/') . '/Hydrator';
        $hydratorNamespace = $baseNamespace . '\\Hydrator';
        $hydratorClass = $hydratorNamespace . '\\' . $name . 'Hydrator';

        $repoFolder = rtrim($folder, '/') . '/Repository';
        $repoNamespace = $baseNamespace . '\\Repository';

        // 1. Make Schema
        $schemaCommand = $app->find('persistence:make-schema');
        $schemaInput = new ArrayInput([
            'name' => $name . 'Schema',
            '--folder' => $schemaFolder,
            '--namespace' => $schemaNamespace,
            '--table' => $tableName,
        ]);
        $schemaCode = $schemaCommand->run($schemaInput, $output);
        if ($schemaCode !== Command::SUCCESS) {
            return $schemaCode;
        }

        // 2. Make Entity
        // We can't easily introspect the schema we just created because it's not loaded.
        // But since it's a fresh schema, we know it only has ID.
        // MakeEntityCommand handles "schema not found" by generating a skeleton.
        // We'll pass the schema class name anyway so it puts the use statement.
        $entityCommand = $app->find('persistence:make-entity');
        $entityInput = new ArrayInput([
            'name' => $name,
            '--schema' => $schemaClass,
            '--folder' => $domainFolder,
            '--namespace' => $domainNamespace,
        ]);
        $entityCode = $entityCommand->run($entityInput, $output);
        if ($entityCode !== Command::SUCCESS) {
            return $entityCode;
        }

        // 3. Make Hydrator
        $hydratorCommand = $app->find('persistence:make-hydrator');
        $hydratorInput = new ArrayInput([
            'name' => $name . 'Hydrator',
            '--schema' => $schemaClass,
            '--entity' => $entityClass,
            '--folder' => $hydratorFolder,
            '--namespace' => $hydratorNamespace,
        ]);
        $hydratorCode = $hydratorCommand->run($hydratorInput, $output);
        if ($hydratorCode !== Command::SUCCESS) {
            return $hydratorCode;
        }

        // 4. Make Repository
        $repoCommand = $app->find('persistence:make-repository');
        $repoInput = new ArrayInput([
            'name' => $name . 'Repository',
            '--hydrator' => $hydratorClass,
            '--folder' => $repoFolder,
            '--namespace' => $repoNamespace,
        ]);
        $repoCode = $repoCommand->run($repoInput, $output);
        if ($repoCode !== Command::SUCCESS) {
            return $repoCode;
        }

        $io->success('Scaffolding complete.');

        return Command::SUCCESS;
    }

    private function guessNamespace(string $path): string
    {
        $path = trim($path, '/');
        if ($path === 'src' || str_starts_with($path, 'src/')) {
            $path = substr($path, 3);
            $path = ltrim($path, '/');
        }

        if ($path === '') {
            return 'App';
        }

        $parts = array_map('ucfirst', explode('/', $path));

        return 'App\\' . implode('\\', $parts);
    }
}
