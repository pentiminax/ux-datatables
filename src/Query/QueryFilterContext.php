<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Query\Intent\ColumnReadReference;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent;

/**
 * Context object passed to Doctrine filters, shared across every filter in the chain for
 * one query build.
 *
 * Carries the normalized, provider-neutral {@see DataTableQueryIntent} (built once),
 * the configured columns indexed by name for Doctrine-specific resolution
 * (raw order expressions, field type checks), and the root alias. Filters read
 * normalized criteria from the intent rather than re-resolving the raw request.
 */
final class QueryFilterContext
{
    private int $paramIndexCursor = 0;

    /**
     * @param array<string, ColumnInterface> $columns Configured columns indexed by name
     */
    public function __construct(
        public readonly DataTableQueryIntent $intent,
        public readonly array $columns,
        public readonly string $alias = 'e',
    ) {
    }

    public function columnByName(string $name): ?ColumnInterface
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * A fresh parameter index, never reused for the lifetime of this context.
     *
     * Column-search, column-control-search, and global-search criteria on the same column
     * can all reach a strategy that names its Doctrine parameter from this index alone
     * (e.g. ComparisonSearchStrategy's column_control_param_N) — indexing by column position
     * let two different search forms on the same column mint the identical parameter name,
     * so the later setParameter() call silently overwrote the earlier one's bound value.
     * A single counter shared by every filter in the chain guarantees each call gets a name
     * no other call can produce, regardless of which filter or how many columns are involved.
     */
    public function nextParamIndex(): int
    {
        return $this->paramIndexCursor++;
    }

    /**
     * @deprecated Use {@see self::nextParamIndex()} instead. Kept callable, rather than
     * removed, for any existing custom {@see \Pentiminax\UX\DataTables\Contracts\QueryFilterInterface}
     * implementation still calling this method directly — removing it outright turned into
     * an undefined-method error instead of a parameter index.
     *
     * $reference is accepted but no longer used to compute the index: this method now
     * draws from the same shared counter as nextParamIndex() rather than $reference's fixed
     * position in the column list. Restoring the old position-based value here would bring
     * back the exact collision nextParamIndex() exists to prevent — a caller still on
     * paramIndexFor() could once again mint the same parameter name as a built-in filter
     * processing the same column, just with the collision moved to a third-party filter
     * instead of two built-in ones.
     */
    public function paramIndexFor(ColumnReadReference $reference): int
    {
        return $this->nextParamIndex();
    }
}
