<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Query\Intent\ColumnReadReference;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent;

/**
 * Immutable context object passed to Doctrine filters.
 *
 * Carries the normalized, provider-neutral {@see DataTableQueryIntent} (built once),
 * the configured columns indexed by name for Doctrine-specific resolution
 * (raw order expressions, field type checks), and the root alias. Filters read
 * normalized criteria from the intent rather than re-resolving the raw request.
 */
final readonly class QueryFilterContext
{
    /**
     * @param array<string, ColumnInterface> $columns Configured columns indexed by name
     */
    public function __construct(
        public DataTableQueryIntent $intent,
        public array $columns,
        public string $alias = 'e',
    ) {
    }

    public function columnByName(string $name): ?ColumnInterface
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * Stable per-query parameter index for a referenced column: its position in the
     * intent's display-ordered column list. Preserves the parameter names produced by
     * the previous configured-column-index based filters.
     */
    public function paramIndexFor(ColumnReadReference $reference): int
    {
        $index = array_search($reference, $this->intent->columns, true);

        return false === $index ? 0 : $index;
    }
}
