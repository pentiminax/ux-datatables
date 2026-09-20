<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Attribute\AttributeTarget;
use Pentiminax\UX\DataTables\Attribute\Column;
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
        ]);
    }

    /**
     * @param class-string $entityClass
     *
     * @return AbstractColumn[]
     */
    public function readColumns(string $entityClass): array
    {
        $targets = $this->memberAttributeReader->readProperties($entityClass, Column::class);

        usort($targets, static function (AttributeTarget $a, AttributeTarget $b): int {
            $positionA = $a->attribute->position ?? 0;
            $positionB = $b->attribute->position ?? 0;

            return $positionA !== $positionB ? $positionA <=> $positionB : $a->declarationOrder <=> $b->declarationOrder;
        });

        return array_map($this->buildColumn(...), $targets);
    }

    /**
     * @param AttributeTarget<Column> $target
     */
    private function buildColumn(AttributeTarget $target): AbstractColumn
    {
        $attribute = $target->attribute;

        $name  = $attribute->name  ?? $target->name;
        $title = $attribute->title ?? $this->propertyNameHumanizer->humanize($target->name);

        $columnClass = $attribute->type ?? $this->propertyTypeMapper->mapType($target->type);

        /** @var AbstractColumn $column */
        $column = $columnClass::new($name, $title);

        $this->optionApplier->apply($column, $this->optionsFrom($attribute, $column));

        return $column;
    }

    /**
     * Translate the attribute's named parameters into the option map the applier consumes.
     *
     * Options the resolved column cannot carry are dropped rather than rejected: `format` only
     * means something to a date column, and the attribute is shared by every column type.
     *
     * @return array<string, mixed>
     */
    private function optionsFrom(Column $attribute, AbstractColumn $column): array
    {
        $options = [
            'orderable'          => $attribute->orderable,
            'searchable'         => $attribute->searchable,
            'visible'            => $attribute->visible,
            'exportable'         => $attribute->exportable,
            'hideWhenUpdating'   => $attribute->hideWhenUpdating,
            'globalSearchable'   => $attribute->globalSearchable,
            'width'              => $attribute->width,
            'responsivePriority' => $attribute->responsivePriority,
            'className'          => $attribute->className,
            'cellType'           => $attribute->cellType,
            'defaultContent'     => $attribute->defaultContent,
            'field'              => $attribute->field,
            'format'             => $attribute->format,
            'renderAsBadges'     => false === $attribute->renderAsBadges ? null : $attribute->renderAsBadges,
        ];

        return array_filter(
            $options,
            fn (mixed $value, string $option): bool => null !== $value && $this->optionApplier->supports($column, $option),
            \ARRAY_FILTER_USE_BOTH,
        );
    }
}
