<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;

final class NormalizationStage implements RowStageInterface
{
    public function process(array $mappedRow, mixed $originalRow, array $columns): array
    {
        foreach ($columns as $column) {
            $key = $column->getData() ?? $column->getName();
            if (null === $key || '' === $key || !\array_key_exists($key, $mappedRow)) {
                continue;
            }

            $value = $mappedRow[$key];
            if (!\is_object($value)) {
                continue;
            }

            $field = $column->getField();
            if (null !== $field && str_contains($field, '.')) {
                $resolved = PropertyReader::readPath($mappedRow, $field);
                if (null !== $resolved && !\is_object($resolved)) {
                    $mappedRow[$key] = $resolved;
                    continue;
                }
            }

            if ($column instanceof DateColumn && $value instanceof \DateTimeInterface) {
                $mappedRow[$key] = $value->format($column->getFormat());
            } elseif ($value instanceof \Stringable) {
                $mappedRow[$key] = (string) $value;
            } else {
                $mappedRow[$key] = null;
            }
        }

        return $mappedRow;
    }
}
