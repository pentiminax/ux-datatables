<?php

namespace Pentiminax\UX\DataTables\RowMapper;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;

final class DefaultRowMapper implements RowMapperInterface
{
    /**
     * @param AbstractColumn[] $columns
     */
    public function __construct(
        private readonly array $columns,
    ) {
    }

    public function map(mixed $row): array
    {
        if (\is_array($row)) {
            return $row;
        }

        if ($row instanceof \JsonSerializable) {
            return $row->jsonSerialize();
        }

        if (!\is_object($row)) {
            return (array) $row;
        }

        return $this->mapObjectRow($row);
    }

    private function mapObjectRow(object $row): array
    {
        $mapped = [];
        foreach ($this->columns as $column) {
            $key = $this->resolveColumnKey($column);
            if (null === $key) {
                continue;
            }

            $value = $this->readValueFromRow($row, $key);
            if ($column instanceof DateColumn && $value instanceof \DateTimeInterface) {
                if ($column->getFormat()) {
                    $value = $value->format($column->getFormat());
                } else {
                    $value = $value->format('Y-m-d');
                }
            }

            $mapped[$key] = $value;
        }

        return $mapped ?: get_object_vars($row);
    }

    private function resolveColumnKey(ColumnInterface $column): ?string
    {
        $key = $column->getData() ?? $column->getName();

        return null === $key || '' === $key ? null : $key;
    }

    private function readValueFromRow(mixed $row, string $path): mixed
    {
        $value = $row;
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
                return $object->$method();
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
