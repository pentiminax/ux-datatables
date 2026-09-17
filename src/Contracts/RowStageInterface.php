<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * One step of {@see \Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline}, refining an
 * already-mapped row before URL, template, and action resolution run.
 *
 * A stage must return a new or modified array and must not mutate $originalRow: the source object
 * is still handed to template columns and action resolvers afterwards. $columns holds only the
 * columns the current user may see.
 *
 * The shipped stage list is internal to DataTableRuntimeFactory and is not extensible through a
 * tag; a table adds its own row work in mapRow(), or in a RowMapperInterface wrapping
 * createRowMapper().
 */
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
