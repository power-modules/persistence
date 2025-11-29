<?php

declare(strict_types=1);

namespace Modular\Persistence\Console;

use Modular\Persistence\Schema\ISchema;
use Modular\Persistence\Schema\ISchemaQueryGenerator;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'persistence:generate-schema',
    description: 'Generates SQL commands from the Schema files and saves them to disk near the schema file.',
)]
final class GenerateSchemaCommand extends Command
{
    public function __construct(
        private readonly ISchemaQueryGenerator $queryGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('schema-class', InputArgument::REQUIRED, 'The fully qualified class name of the schema.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $schemaClass = $input->getArgument('schema-class');

        if (!class_exists($schemaClass)) {
            $io->error(sprintf('Class "%s" does not exist.', $schemaClass));

            return Command::FAILURE;
        }

        if (!is_subclass_of($schemaClass, ISchema::class)) {
            $io->error(sprintf('Class "%s" must implement "%s".', $schemaClass, ISchema::class));

            return Command::FAILURE;
        }

        // Get the first case of the enum to pass to the generator
        // We assume ISchema is an Enum as per interface definition (cases() method)
        $cases = $schemaClass::cases();
        if (empty($cases)) {
            $io->error(sprintf('Schema "%s" has no cases (columns).', $schemaClass));

            return Command::FAILURE;
        }
        $schemaInstance = $cases[0];

        $queries = $this->queryGenerator->generate($schemaInstance);

        $sqlContent = implode("\n", iterator_to_array($queries));

        $reflection = new ReflectionClass($schemaClass);
        $fileName = $reflection->getFileName();

        if ($fileName === false) {
            $io->error(sprintf('Could not determine file path for class "%s".', $schemaClass));

            return Command::FAILURE;
        }

        $sqlFile = preg_replace('/\.php$/', '.sql', $fileName);

        if ($sqlFile === null) {
            $io->error(sprintf('Error determining SQL file path for "%s".', $fileName));

            return Command::FAILURE;
        }

        if (file_put_contents($sqlFile, $sqlContent) === false) {
            $io->error(sprintf('Failed to write to file "%s".', $sqlFile));

            return Command::FAILURE;
        }

        $io->success(sprintf('Generated SQL for "%s" at "%s".', $schemaClass, $sqlFile));

        return Command::SUCCESS;
    }
}
