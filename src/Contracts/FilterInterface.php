<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A user-facing filter declared via AbstractDataTable::configureFilters().
 *
 * Each filter renders a control in the Stimulus-built filter bar and applies a
 * Doctrine condition server-side. The value comes from the AJAX request keyed by
 * the filter name. Filters must ignore empty/irrelevant values (no-op).
 */
interface FilterInterface extends \JsonSerializable
{
    /**
     * Unique filter name, used as the AJAX payload key (filters[name]).
     */
    public function getName(): string;

    /**
     * Apply the filter condition to the QueryBuilder for the given submitted value.
     *
     * Implementations must be a no-op when $value is empty or not applicable.
     */
    public function apply(QueryBuilder $qb, mixed $value, string $alias): void;

    /**
     * Translate the labels exposed by the filter at render time.
     *
     * Labels, placeholders, and type-specific strings (choice options, ternary
     * states) are resolved lazily so the translator stays out of user-facing
     * configuration code (configureFilters()); translation is applied by the
     * RenderingPreparer, mirroring how column titles are handled. A filter
     * without translatable strings implements this as a no-op.
     */
    public function translateLabels(TranslatorInterface $translator, ?string $locale = null): void;

    /**
     * Client-side definition consumed by the Stimulus controller.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;
}
