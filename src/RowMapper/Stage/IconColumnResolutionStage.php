<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;

final class IconColumnResolutionStage implements RowStageInterface
{
    public const string ROW_ICONS_KEY = '__ux_datatables_icons';

    public function process(array $mappedRow, mixed $originalRow, array $columns): array
    {
        foreach ($columns as $column) {
            if (!$column instanceof IconColumn || !$column->hasResolvers()) {
                continue;
            }

            $key = $column->getData() ?? $column->getName();
            if ('' === $key) {
                continue;
            }

            $state = $mappedRow[$key] ?? null;
            $data  = $column->resolveIconData($state);

            if ([] === $data) {
                continue;
            }

            $mappedRow[self::ROW_ICONS_KEY][$key] = $data;
        }

        return $mappedRow;
    }
}
