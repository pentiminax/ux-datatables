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
    public function renderRow(array $row, mixed $mappedRow, iterable $columns): array
    {
        $renderedRow = $row;
        $contextRow  = $row;

        foreach ($columns as $column) {
            if (!$column instanceof TemplateColumn) {
                continue;
            }

            $field = $column->getField();
            $data  = $this->resolveData(mappedRow: $mappedRow, row: $contextRow, field: $field);

            $renderedRow[$field] = $this->renderTemplate($column->getTemplate(), [
                'entity' => $mappedRow,
                'data'   => $data,
                'column' => $column->jsonSerialize(),
                'row'    => $contextRow,
            ]);
        }

        return $renderedRow;
    }

    private function renderTemplate(string $template, array $context): string
    {
        if (null === $this->twig) {
            throw new \LogicException('Twig Environment is required to render TemplateColumn cells.');
        }

        return $this->twig->render($template, $context);
    }

    private function resolveData(mixed $mappedRow, array $row, string $field): mixed
    {
        $value = $this->readPath($row, $field);
        if (null !== $value) {
            return $value;
        }

        return $this->readPath($mappedRow, $field);
    }

    private function readPath(mixed $value, string $path): mixed
    {
        if ('' === $path) {
            return null;
        }

        foreach (explode('.', $path) as $segment) {
            if (\is_array($value)) {
                if (!isset($value[$segment])) {
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
