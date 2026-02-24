<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Twig\Environment;

final class TemplateColumnRenderer
{
    public function __construct(
        private readonly ?Environment $twig = null,
    ) {
    }

    /**
     * @param iterable<ColumnInterface> $columns
     */
    public function renderRow(array $row, mixed $entity, iterable $columns): array
    {
        $renderedRow = $row;
        $contextRow  = $row;

        foreach ($columns as $column) {
            if (!$column instanceof TemplateColumn) {
                continue;
            }

            $outputKey = $this->resolveOutputKey($column->getData(), $column->getName());
            if (null === $outputKey) {
                continue;
            }

            $field = $this->resolveOutputKey($column->getField(), $outputKey) ?? $outputKey;
            $value = $this->resolveValue(entity: $entity, row: $contextRow, field: $field, outputKey: $outputKey);

            $renderedRow[$outputKey] = $this->renderTemplate($column->getTemplate(), [
                'entity' => $entity,
                'value'  => $value,
                'column' => $column->jsonSerialize(),
                'row'    => $contextRow,
            ]);
        }

        return $renderedRow;
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<int, mixed> $columns
     *
     * @return array<int, mixed>
     */
    public function renderInlineData(array $rows, array $columns): array
    {
        $templateColumns = $this->resolveTemplateColumns($columns);
        if ([] === $templateColumns) {
            return $rows;
        }

        foreach ($rows as $index => $row) {
            $arrayRow = $this->normalizeRow($row);
            if (null === $arrayRow) {
                continue;
            }

            $rows[$index] = $this->renderInlineRow($arrayRow, $row, $templateColumns);
        }

        return $rows;
    }

    /**
     * @param array<int, array{template:string, field:string, outputKey:string, column:array<string, mixed>}> $templateColumns
     */
    private function renderInlineRow(array $row, mixed $entity, array $templateColumns): array
    {
        $renderedRow = $row;
        $contextRow  = $row;

        foreach ($templateColumns as $templateColumn) {
            $value = $this->resolveValue(
                entity: $entity,
                row: $contextRow,
                field: $templateColumn['field'],
                outputKey: $templateColumn['outputKey']
            );

            $renderedRow[$templateColumn['outputKey']] = $this->renderTemplate($templateColumn['template'], [
                'entity' => $entity,
                'value'  => $value,
                'column' => $templateColumn['column'],
                'row'    => $contextRow,
            ]);
        }

        return $renderedRow;
    }

    /**
     * @param array<int, mixed> $columns
     *
     * @return array<int, array{template:string, field:string, outputKey:string, column:array<string, mixed>}>
     */
    private function resolveTemplateColumns(array $columns): array
    {
        $templateColumns = [];

        foreach ($columns as $column) {
            if (!\is_array($column)) {
                continue;
            }

            $template = $column[TemplateColumn::OPTION_TEMPLATE_PATH] ?? null;
            if (!\is_string($template)) {
                continue;
            }

            $template = trim($template);
            if ('' === $template) {
                throw new \InvalidArgumentException('Template path cannot be empty.');
            }

            $outputKey = $this->resolveOutputKey(
                \is_string($column['data'] ?? null) ? $column['data'] : null,
                \is_string($column['name'] ?? null) ? $column['name'] : null
            );
            if (null === $outputKey) {
                continue;
            }

            $field = $this->resolveOutputKey(
                \is_string($column['field'] ?? null) ? $column['field'] : null,
                $outputKey
            ) ?? $outputKey;

            $templateColumns[] = [
                'template'  => $template,
                'field'     => $field,
                'outputKey' => $outputKey,
                'column'    => $column,
            ];
        }

        return $templateColumns;
    }

    private function renderTemplate(string $template, array $context): string
    {
        if (null === $this->twig) {
            throw new \LogicException('Twig Environment is required to render TemplateColumn cells.');
        }

        return $this->twig->render($template, $context);
    }

    private function resolveValue(mixed $entity, array $row, string $field, string $outputKey): mixed
    {
        $value = $this->readPath($entity, $field);
        if (null !== $value) {
            return $value;
        }

        $value = $this->readPath($row, $field);
        if (null !== $value) {
            return $value;
        }

        $value = $this->readPath($row, $outputKey);
        if (null !== $value) {
            return $value;
        }

        return $this->readPath($entity, $outputKey);
    }

    private function resolveOutputKey(?string $preferred, ?string $fallback): ?string
    {
        foreach ([$preferred, $fallback] as $candidate) {
            if (!\is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);
            if ('' !== $candidate) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeRow(mixed $row): ?array
    {
        if (\is_array($row)) {
            return $row;
        }

        if ($row instanceof \JsonSerializable) {
            $serialized = $row->jsonSerialize();
            if (\is_array($serialized)) {
                return $serialized;
            }
        }

        if (\is_object($row)) {
            return get_object_vars($row);
        }

        return null;
    }

    private function readPath(mixed $value, string $path): mixed
    {
        if ('' === $path) {
            return null;
        }

        foreach (explode('.', $path) as $segment) {
            if (\is_array($value)) {
                if (!\array_key_exists($segment, $value)) {
                    return null;
                }

                $value = $value[$segment];
                continue;
            }

            if (\is_object($value)) {
                $value = $this->readObjectValue($value, $segment);
                continue;
            }

            return null;
        }

        return $value;
    }

    private function readObjectValue(object $object, string $property): mixed
    {
        if (\is_callable([$object, $property])) {
            return $object->$property();
        }

        $accessor = $this->buildAccessorSuffix($property);
        foreach (['get', 'is', 'has'] as $prefix) {
            $method = $prefix.$accessor;
            if (\is_callable([$object, $method])) {
                $value = $object->$method();
                if (\is_object($value) && $value instanceof \Stringable) {
                    return (string) $value;
                }

                return $value;
            }
        }

        if (property_exists($object, $property)) {
            $reflection = new \ReflectionObject($object);
            if (!$reflection->hasProperty($property) || $reflection->getProperty($property)->isPublic()) {
                return $object->$property;
            }
        }

        return null;
    }

    private function buildAccessorSuffix(string $property): string
    {
        if (str_contains($property, '_') || str_contains($property, '-')) {
            $property = str_replace(['-', '_'], ' ', $property);
            $property = str_replace(' ', '', ucwords($property));
        }

        return ucfirst($property);
    }
}
