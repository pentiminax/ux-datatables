<?php

namespace Pentiminax\UX\DataTables\Maker;

use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\ApiPlatform\PropertyNameHumanizer;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class MakeDataTable extends AbstractMaker
{
    public function __construct(
        private readonly PropertyNameHumanizer $propertyNameHumanizer = new PropertyNameHumanizer(),
        private readonly ?ManagerRegistry $managerRegistry = null,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:datatable';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a DataTable configuration for a given Doctrine entity.';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setDescription(self::getCommandDescription())
            ->addArgument('entity', InputArgument::OPTIONAL, 'Entity class to create a DataTable for');

        $inputConfig->setArgumentAsNonInteractive('entity');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if ($input->getArgument('entity')) {
            return;
        }

        $argument = $command->getDefinition()->getArgument('entity');
        $entity   = $io->choice($argument->getDescription(), $this->entityChoices());

        $input->setArgument('entity', $entity);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        /** @var class-string $class */
        $class = $input->getArgument('entity');

        if (!class_exists($class)) {
            $class = $generator->createClassNameDetails($class, 'Entity\\')->getFullName();
        }

        if (!class_exists($class)) {
            /** @var class-string $entityArg */
            $entityArg = $input->getArgument('entity');

            throw new RuntimeCommandException(\sprintf('Entity "%s" not found.', \is_string($entityArg) ? $entityArg : 'unknown'));
        }

        $entityShortName  = $this->getShortClassName($class);
        $classNameDetails = $generator->createClassNameDetails(
            $entityShortName,
            'DataTables\\',
            'DataTable'
        );

        $columns    = $this->guessColumns($class);
        $columnUses = $this->getColumnUses($columns);

        $generator->generateClass(
            $classNameDetails->getFullName(),
            __DIR__.'/../Resources/skeleton/datatable/DataTable.tpl.php',
            [
                'entity_class_name' => $class,
                'entity_short_name' => $entityShortName,
                'columns'           => $columns,
                'column_uses'       => $columnUses,
            ]
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text(\sprintf('Next: review the columns in <info>%s</info>.', $classNameDetails->getFullName()));
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // No dependencies needed
    }

    private function getShortClassName(string $className): string
    {
        $className = ltrim($className, '\\');
        $position  = strrpos($className, '\\');

        return false === $position ? $className : substr($className, $position + 1);
    }

    /**
     * @return array<int, array{name: string, label: string, column_class: string, column_class_short: string}>
     */
    private function guessColumns(string $entityClass): array
    {
        $reflection = new \ReflectionClass($entityClass);
        $columns    = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $columnClass = $this->guessColumnClass($property);
            if (null === $columnClass) {
                continue;
            }

            $name      = $property->getName();
            $columns[] = [
                'name'               => $this->escapeSingleQuotes($name),
                'label'              => $this->escapeSingleQuotes($this->propertyNameHumanizer->humanize($name)),
                'column_class'       => $columnClass,
                'column_class_short' => $this->getShortClassName($columnClass),
            ];
        }

        return $columns;
    }

    private function guessColumnClass(\ReflectionProperty $property): ?string
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return TextColumn::class;
        }

        $typeName = $type->getName();
        if ($type->isBuiltin()) {
            return match ($typeName) {
                'int', 'float' => NumberColumn::class,
                'string' => TextColumn::class,
                'bool'   => BooleanColumn::class,
                default  => null,
            };
        }

        if (is_a($typeName, \DateTimeInterface::class, true)) {
            return DateColumn::class;
        }

        return null;
    }

    /**
     * @param array<int, array{column_class: string}> $columns
     *
     * @return array<int, string>
     */
    private function getColumnUses(array $columns): array
    {
        if ([] === $columns) {
            return [TextColumn::class];
        }

        $uses = [];
        foreach ($columns as $column) {
            $uses[] = $column['column_class'];
        }

        return array_values(array_unique($uses));
    }

    private function escapeSingleQuotes(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    /**
     * @return string[]
     */
    private function entityChoices(): array
    {
        $choices = [];

        foreach ($this->managerRegistry?->getManagers() ?? [] as $manager) {
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                $choices[] = $metadata->getName();
            }
        }

        sort($choices);

        if (empty($choices)) {
            throw new RuntimeCommandException('No entities found.');
        }

        return $choices;
    }
}
