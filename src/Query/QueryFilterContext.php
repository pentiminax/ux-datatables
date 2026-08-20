<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
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
}
