<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * A column that decides whether server-side LIKE searches normalize case.
 *
 * By default the query pipeline compares LOWER(field) against a lowercased term, so a search
 * behaves the same on PostgreSQL, on MySQL, and on binary collations. Returning false restores a
 * bare LIKE on the raw column: the comparison then follows the column's collation, and a plain
 * or prefix index on the column stays usable.
 *
 * {@see \Pentiminax\UX\DataTables\Column\AbstractColumn} implements this and exposes it as
 * searchNormalization().
 *
 * @deprecated since 1.6, extend AbstractColumn instead. Removed in 2.0.
 */
interface NormalizedSearchColumnInterface extends ColumnInterface
{
    public function isSearchNormalized(): bool;
}
