<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Attribute\Column;

final class AttributeColumnReader
{
    public function __construct(
        private readonly PropertyTypeMapper $propertyTypeMapper = new PropertyTypeMapper(),
    ) {
    }

    /**
     * Read #[Column] attributes from an entity class and build column instances.
     *
     * @return AbstractColumn[]
     */
    public function readColumns(string $entityClass): array
    {
        $reflectionClass = new \ReflectionClass($entityClass);

        $annotated = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes(Column::class);

            if (empty($attributes)) {
                continue;
            }

            $attr        = $attributes[0]->newInstance();
            $annotated[] = [$property, $attr];
        }

        usort($annotated, static fn (array $a, array $b) => $a[1]->priority <=> $b[1]->priority);

        $columns = [];

        foreach ($annotated as [$property, $attr]) {
            $columns[] = $this->buildColumn($property, $attr);
        }

        return $columns;
    }

    private function buildColumn(\ReflectionProperty $property, Column $attr): AbstractColumn
    {
        $name  = $attr->name  ?? $property->getName();
        $label = $attr->title ?? $this->humanize($property->getName());

        $columnClass = $attr->type ?? $this->resolveColumnClass($property);

        /** @var AbstractColumn $column */
        $column = $columnClass::new($name, $label);

        $column->setOrderable($attr->orderable);
        $column->setSearchable($attr->searchable);
        $column->setVisible($attr->visible);
        $column->setExportable($attr->exportable);

        if (!$attr->globalSearchable) {
            $column->disableGlobalSearch();
        }

        if (null !== $attr->width) {
            $column->setWidth($attr->width);
        }

        if (null !== $attr->className) {
            $column->setClassName($attr->className);
        }

        if (null !== $attr->cellType) {
            $column->setCellType($attr->cellType);
        }

        if (null !== $attr->render) {
            $column->setRender($attr->render);
        }

        if (null !== $attr->defaultContent) {
            $column->setDefaultContent($attr->defaultContent);
        }

        if (null !== $attr->field) {
            $column->setField($attr->field);
        }

        if (null !== $attr->format && $column instanceof DateColumn) {
            $column->setFormat($attr->format);
        }

        return $column;
    }

    /**
     * @return class-string<AbstractColumn>
     */
    private function resolveColumnClass(\ReflectionProperty $property): string
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return TextColumn::class;
        }

        return $this->propertyTypeMapper->mapType($type);
    }

    private function humanize(string $name): string
    {
        $label = str_replace(['_', '-'], ' ', $name);
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $label);
        $label = trim($label);
        $label = ucwords($label);
        $label = preg_replace('/\bId\b/', 'ID', $label);

        return '' === $label ? $name : $label;
    }
}
