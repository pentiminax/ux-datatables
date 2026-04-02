<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;

final class RowProcessingPipeline implements RowMapperInterface
{
    /**
     * @param ColumnInterface[]     $columns
     * @param \Closure(mixed):array $baseMapper
     */
    public function __construct(
        private readonly \Closure $baseMapper,
        private readonly array $columns,
        private readonly ?TemplateColumnRenderer $templateColumnRenderer = null,
        private readonly ?ActionRowDataResolver $actionRowDataResolver = null,
    ) {
    }

    public function map(mixed $row): array
    {
        $mappedRow = ($this->baseMapper)($row);

        $mappedRow = $this->normalizeRow($mappedRow);

        if (null !== $this->templateColumnRenderer) {
            $mappedRow = $this->templateColumnRenderer->renderRow(
                row: $mappedRow,
                mappedRow: $row,
                columns: $this->columns,
            );
        }

        if (null !== $this->actionRowDataResolver) {
            $mappedRow = $this->actionRowDataResolver->resolveRow($mappedRow, $row, $this->columns);
        }

        return $mappedRow;
    }

    private function normalizeRow(array $mappedRow): array
    {
        foreach ($this->columns as $column) {
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
