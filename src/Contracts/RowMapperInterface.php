<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * Turns one source row -- a Doctrine entity, a DTO, or an array -- into the flat, column-keyed
 * array the client receives.
 *
 * Implementations must be pure and per-row: no state carried between calls, no dependence on which
 * other rows share the page, since the same mapper serves a page, an inline render, and an export
 * batch. Keys must match the columns' data keys, or the cell renders empty.
 *
 * AbstractDataTable builds the bundle's own mapper (a {@see RowStageInterface} pipeline plus
 * template and action resolution) in the final createRowMapper(); override mapRow() to change the
 * base mapping. Implement this interface to wrap that mapper -- an anonymous class delegating to
 * it is enough -- and pass the result to a provider built in createDataProvider().
 */
interface RowMapperInterface
{
    public function map(mixed $row): array;
}
