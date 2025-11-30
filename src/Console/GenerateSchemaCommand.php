<?php

declare(strict_types=1);

namespace Modular\Persistence\Console;

use Modular\Persistence\Schema\Contract\ISchema;
use Modular\Persistence\Schema\Contract\ISchemaQueryGenerator;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

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
        $this->addArgument('target', InputArgument::REQUIRED, 'The fully qualified class name of the schema or a directory to scan.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = $input->getArgument('target');

        $schemas = [];

        if (is_dir($target)) {
            $finder = new Finder();
            $finder->files()->in($target)->name('*.php');

            foreach ($finder as $file) {
                $classes = $this->getClassNamesFromFile($file->getRealPath());
                foreach ($classes as $class) {
                    if (class_exists($class) && is_subclass_of($class, ISchema::class)) {
                        $schemas[] = $class;
                    }
                }
            }

            if (empty($schemas)) {
                $io->warning(sprintf('No schemas found in directory "%s".', $target));

                return Command::SUCCESS;
            }
        } else {
            if (!class_exists($target)) {
                $io->error(sprintf('Class or directory "%s" does not exist.', $target));

                return Command::FAILURE;
            }

            if (!is_subclass_of($target, ISchema::class)) {
                $io->error(sprintf('Class "%s" must implement "%s".', $target, ISchema::class));

                return Command::FAILURE;
            }
            $schemas[] = $target;
        }

        $hasError = false;
        foreach ($schemas as $schemaClass) {
            if (!$this->generateForSchema($schemaClass, $io)) {
                $hasError = true;
            }
        }

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param class-string $schemaClass
     */
    private function generateForSchema(string $schemaClass, SymfonyStyle $io): bool
    {
        // Get the first case of the enum to pass to the generator
        // We assume ISchema is an Enum as per interface definition (cases() method)
        $cases = $schemaClass::cases();

        if (empty($cases)) {
            $io->error(sprintf('Schema "%s" has no cases (columns).', $schemaClass));

            return false;
        }

        $schemaInstance = $cases[0];

        $queries = $this->queryGenerator->generate($schemaInstance);

        $sqlContent = implode("\n", iterator_to_array($queries));

        $reflection = new ReflectionClass($schemaClass);
        $fileName = $reflection->getFileName();

        if ($fileName === false) {
            $io->error(sprintf('Could not determine file path for class "%s".', $schemaClass));

            return false;
        }

        $sqlFile = preg_replace('/\.php$/', '.sql', $fileName);

        if ($sqlFile === null) {
            $io->error(sprintf('Error determining SQL file path for "%s".', $fileName));

            return false;
        }

        if (file_put_contents($sqlFile, $sqlContent) === false) {
            $io->error(sprintf('Failed to write to file "%s".', $sqlFile));

            return false;
        }

        $io->success(sprintf('Generated SQL for "%s" at "%s".', $schemaClass, $sqlFile));

        return true;
    }

    /**
     * @return array<string>
     */
    private function getClassNamesFromFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }
        $tokens = token_get_all($content);
        $classes = [];
        $namespace = '';

        for ($i = 0; $i < count($tokens); $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_NAMESPACE) {
                $i++;
                while (isset($tokens[$i]) && is_array($tokens[$i]) && ($tokens[$i][0] === T_WHITESPACE || $tokens[$i][0] === T_COMMENT || $tokens[$i][0] === T_DOC_COMMENT)) {
                    $i++;
                }

                while (isset($tokens[$i])) {
                    if (is_array($tokens[$i])) {
                        if (in_array($tokens[$i][0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                            $namespace .= $tokens[$i][1];
                        }
                    } elseif ($tokens[$i] === ';' || $tokens[$i] === '{') {
                        break;
                    }
                    $i++;
                }
                $namespace = trim($namespace);
            }

            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                $i++;
                while (isset($tokens[$i]) && is_array($tokens[$i]) && ($tokens[$i][0] === T_WHITESPACE || $tokens[$i][0] === T_COMMENT || $tokens[$i][0] === T_DOC_COMMENT)) {
                    $i++;
                }

                if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $classes[] = $namespace ? $namespace . '\\' . $tokens[$i][1] : $tokens[$i][1];
                }
            }
        }

        return $classes;
    }
}
