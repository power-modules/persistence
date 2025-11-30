<?php

declare(strict_types=1);

namespace Modular\Persistence\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            ->addArgument('name', InputArgument::REQUIRED, 'The domain name (e.g. User)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $app = $this->getApplication();

        if (!$app) {
            $io->error('Application instance not found.');

            return Command::FAILURE;
        }

        // 1. Make Schema
        $schemaCommand = $app->find('persistence:make-schema');
        $schemaInput = new ArrayInput([
            'name' => $name . 'Schema',
            '--folder' => 'src/Schema',
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
            '--schema' => 'App\\Schema\\' . $name . 'Schema',
            '--folder' => 'src/Domain',
        ]);
        $entityCode = $entityCommand->run($entityInput, $output);
        if ($entityCode !== Command::SUCCESS) {
            return $entityCode;
        }

        // 3. Make Hydrator
        $hydratorCommand = $app->find('persistence:make-hydrator');
        $hydratorInput = new ArrayInput([
            'name' => $name . 'Hydrator',
            '--schema' => 'App\\Schema\\' . $name . 'Schema',
            '--entity' => 'App\\Domain\\' . $name,
            '--folder' => 'src/Hydrator',
        ]);
        $hydratorCode = $hydratorCommand->run($hydratorInput, $output);
        if ($hydratorCode !== Command::SUCCESS) {
            return $hydratorCode;
        }

        // 4. Make Repository
        $repoCommand = $app->find('persistence:make-repository');
        $repoInput = new ArrayInput([
            'name' => $name . 'Repository',
            '--hydrator' => 'App\\Hydrator\\' . $name . 'Hydrator',
            '--folder' => 'src/Repository',
        ]);
        $repoCode = $repoCommand->run($repoInput, $output);
        if ($repoCode !== Command::SUCCESS) {
            return $repoCode;
        }

        $io->success('Scaffolding complete.');

        return Command::SUCCESS;
    }
}
