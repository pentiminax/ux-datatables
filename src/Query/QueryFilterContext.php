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

    /** @var array<int, int> spl_object_id(ColumnReadReference) => index, scoped to one filter */
    private array $paramIndexScope = [];

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
     * A parameter index stable within one filter's apply() call: requesting the same
     * $reference more than once while building and binding a single parameter returns the
     * same index every time, so the Doctrine placeholder and the setParameter() call keep
     * agreeing. Use this instead of {@see self::nextParamIndex()} when one QueryFilterInterface
     * implementation needs to reference one bound value from more than one DQL fragment.
     *
     * {@see QueryFilterPipeline} clears that stability between filters, so a second
     * filter processing the same column still draws a genuinely fresh index from the shared
     * counter — the stability here is scoped to a single apply() call and never leaks across
     * filter boundaries, which is what keeps two different filters from colliding on the
     * same column.
     */
    public function paramIndexFor(ColumnReadReference $reference): int
    {
        return $this->paramIndexScope[spl_object_id($reference)] ??= $this->nextParamIndex();
    }

    /**
     * @internal called by {@see QueryFilterPipeline} between filters so
     * paramIndexFor()'s per-reference stability is scoped to a single filter's apply() call
     * and never leaks across filter boundaries
     */
    public function resetParamIndexScope(): void
    {
        $this->paramIndexScope = [];
    }
}
