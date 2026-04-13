<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

interface RowStageInterface
{
    /**
     * @param array<string, mixed> $mappedRow
     * @param ColumnInterface[]    $columns
     *
     * @return array<string, mixed>
     */
    public function process(array $mappedRow, mixed $originalRow, array $columns): array;
}
