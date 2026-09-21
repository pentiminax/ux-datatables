<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Attribute\AttributeTarget;
use Pentiminax\UX\DataTables\Attribute\Column;
use Pentiminax\UX\DataTables\Attribute\DataTableColumn;
use Pentiminax\UX\DataTables\Attribute\MemberAttributeReader;
use Pentiminax\UX\DataTables\Attribute\OptionApplier;

final class AttributeColumnReader
{
    private readonly OptionApplier $optionApplier;

    public function __construct(
        private readonly PropertyNameHumanizer $propertyNameHumanizer = new PropertyNameHumanizer(),
        private readonly PropertyTypeMapper $propertyTypeMapper = new PropertyTypeMapper(),
        private readonly MemberAttributeReader $memberAttributeReader = new MemberAttributeReader(),
    ) {
        $this->optionApplier = new OptionApplier([
            'globalSearchable' => static function (AbstractColumn $column, bool $searchable): void {
                if (!$searchable) {
                    $column->disableGlobalSearch();
                }
            },
            // Join conditions stay with the fluent API: they are the part an attribute cannot spell.
            'searchJoins' => static function (AbstractColumn $column, array $joins): void {
                foreach ($joins as $join => $alias) {
                    $column->addSearchJoin($join, $alias);
                }
            },
            'customOptions' => static function (AbstractColumn $column, array $customOptions): void {
                foreach ($customOptions as $option => $value) {
                    $column->setCustomOption($option, $value);
                }
            },
            'searchNormalized' => static function (AbstractColumn $column, bool $normalized): void {
                $column->setSearchNormalization($normalized);
            },
        ]);
    }

    /**
     * Columns declared on the members of the class holding the data.
     *
     * @param class-string $entityClass
     *
     * @return AbstractColumn[]
     */
    public function readColumns(string $entityClass): array
    {
        return $this->buildColumns($this->memberAttributeReader->readMembers($entityClass, DataTableColumn::class));
    }

    /**
     * Columns declared on a table class, for the ones no member of the data class backs.
     *
     * @param class-string $dataTableClass
     *
     * @return AbstractColumn[]
     */
    public function readClassColumns(string $dataTableClass): array
    {
        return $this->buildColumns($this->memberAttributeReader->readClass($dataTableClass, DataTableColumn::class));
    }

    /**
     * @param list<AttributeTarget<DataTableColumn>> $targets
     *
     * @return AbstractColumn[]
     */
    private function buildColumns(array $targets): array
    {
        usort($targets, static function (AttributeTarget $a, AttributeTarget $b): int {
            $positionA = $a->attribute->position ?? 0;
            $positionB = $b->attribute->position ?? 0;

            return $positionA !== $positionB ? $positionA <=> $positionB : $a->declarationOrder <=> $b->declarationOrder;
        });

        $columns = array_map($this->buildColumn(...), $targets);

        $this->assertUniqueNames($columns);

        return $columns;
    }

    /**
     * Repeatable declarations make two columns able to claim the same key, and the second one would
     * quietly replace the first once the payload is keyed by name.
     *
     * @param AbstractColumn[] $columns
     */
    private function assertUniqueNames(array $columns): void
    {
        $seen = [];

        foreach ($columns as $column) {
            $name = $column->getName();

            if (isset($seen[$name])) {
                throw new \InvalidArgumentException(\sprintf('Two columns are declared under the name "%s".', $name));
            }

            $seen[$name] = true;
        }
    }

    /**
     * @param AttributeTarget<DataTableColumn> $target
     */
    private function buildColumn(AttributeTarget $target): AbstractColumn
    {
        $attribute = $target->attribute;

        $name = $attribute->name ?? $target->name;

        if ('' === $name) {
            throw new \InvalidArgumentException(\sprintf('A "%s" attribute declared on a class must carry a name, since no member gives it one.', DataTableColumn::class));
        }

        // The label reads off the member, not the column key: renaming the key to `full_name` on a
        // `firstName` property never meant relabeling the column.
        $options = $attribute->options;
        $title   = $options['title'] ?? $this->propertyNameHumanizer->humanize('' !== $target->name ? $target->name : $name);
        unset($options['title']);

        $columnClass = $attribute->type ?? $this->propertyTypeMapper->mapType($target->type);

        if (!is_a($columnClass, AbstractColumn::class, true)) {
            throw new \InvalidArgumentException(\sprintf('The type "%s" declared for column "%s" must be a class extending "%s".', $columnClass, $name, AbstractColumn::class));
        }

        /** @var AbstractColumn $column */
        $column = $columnClass::new($name, $title);

        $this->optionApplier->apply($column, $this->applicableOptions($attribute, $column, $options));

        return $column;
    }

    /**
     * The deprecated attribute names every option it knows, whatever the column type ends up being,
     * so `format` on a text column has always been ignored rather than rejected. An option spelled
     * out by hand on the current attribute is a different matter and reaches the applier, which
     * says so when nothing answers it.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function applicableOptions(DataTableColumn $attribute, AbstractColumn $column, array $options): array
    {
        if (!$attribute instanceof Column) {
            return $options;
        }

        return array_filter(
            $options,
            fn (string $option): bool => $this->optionApplier->supports($column, $option),
            \ARRAY_FILTER_USE_KEY,
        );
    }
}
