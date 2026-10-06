<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ColumnKeyResolver;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;
use Pentiminax\UX\DataTables\RowMapper\RowContext;

final class ValueFormattingStage implements RowStageInterface
{
    public function process(array $mappedRow, mixed $originalRow, array $columns): array
    {
        $source = $originalRow instanceof RowContext ? $originalRow->source : $originalRow;

        foreach ($columns as $column) {
            if (!$column instanceof AbstractColumn) {
                continue;
            }

            $formatter = $column->getValueFormatter();
            $key       = ColumnKeyResolver::rowKey($column);
            if (null === $formatter || null === $key) {
                continue;
            }

            $mappedRow[$key] = $formatter($mappedRow[$key] ?? null, $source);
        }

        return $mappedRow;
    }
}
