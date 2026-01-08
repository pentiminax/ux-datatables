<?php

namespace Pentiminax\UX\DataTables\Maker;

use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class MakeDataTable extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:datatable';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a DataTable class for a Doctrine entity';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command->addArgument(
            name: 'entity-class',
            mode: InputArgument::REQUIRED,
            description: 'The entity class name (e.g. <fg=yellow>User</>)'
        );

        $command->setHelp('Creates a DataTable class in App\\DataTables for the given entity.');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $entityArgument = (string) $input->getArgument('entity-class');
        $entityClass    = $this->resolveEntityClass($entityArgument);

        if (!class_exists($entityClass)) {
            throw new \RuntimeException(sprintf('Entity class "%s" was not found. Pass the full class name or ensure it exists.', $entityClass));
        }

        $entityShortName  = $this->getShortClassName($entityClass);
        $classNameDetails = $generator->createClassNameDetails(
            $entityShortName,
            'DataTables\\',
            'DataTable'
        );

        $columns    = $this->guessColumns($entityClass);
        $columnUses = $this->getColumnUses($columns);

        $generator->generateClass(
            $classNameDetails->getFullName(),
            __DIR__.'/../Resources/skeleton/datatable/DataTable.tpl.php',
            [
                'entity_class_name' => $entityClass,
                'entity_short_name' => $entityShortName,
                'columns'           => $columns,
                'column_uses'       => $columnUses,
            ]
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $io->text(sprintf('Next: review the columns in <info>%s</info>.', $classNameDetails->getFullName()));
    }

    private function resolveEntityClass(string $entityArgument): string
    {
        $entityArgument = trim($entityArgument);

        if (str_contains($entityArgument, '\\')) {
            return ltrim($entityArgument, '\\');
        }

        return 'App\\Entity\\'.$entityArgument;
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
                'label'              => $this->escapeSingleQuotes($this->humanize($name)),
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
                'string', 'bool' => TextColumn::class,
                default => null,
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

    private function humanize(string $name): string
    {
        $label = str_replace(['_', '-'], ' ', $name);
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $label);
        $label = trim($label ?? '');
        $label = ucwords($label);
        $label = preg_replace('/\\bId\\b/', 'ID', $label);

        return '' === $label ? $name : $label;
    }

    private function escapeSingleQuotes(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
