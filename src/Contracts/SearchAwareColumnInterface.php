<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * Opt-in contract for columns that carry search-specific field and join configuration.
 *
 * Implement this interface to declare a dedicated search field path and any LEFT JOINs
 * required to resolve that path, independently of the column's display field, row
 * mapping, and client payload.
 *
 * All built-in column types implement this interface through {@see AbstractColumn}.
 * Custom column classes that extend a concrete column type (e.g. TextColumn) inherit
 * the implementation automatically.
 *
 * This interface exists as a separate opt-in contract rather than extending
 * {@see ColumnInterface} to avoid breaking changes for downstream implementations.
 * ColumnInterface is marked as a public API with an explicit stability notice, so
 * adding required methods to it would break any custom column that implements it
 * directly without extending AbstractColumn.
 *
 * Custom classes that implement {@see ColumnInterface} directly are not required to
 * implement this interface. Search filters will fall back to {@see ColumnInterface::getField()}
 * and apply no additional joins for columns that do not implement SearchAwareColumnInterface.
 *
 * @see AbstractColumn::setSearchField()
 * @see AbstractColumn::addSearchJoin()
 */
interface SearchAwareColumnInterface
{
    /**
     * Return the dot-notation field path used exclusively for search resolution.
     *
     * When non-null, search filters and strategies use this path instead of
     * {@see ColumnInterface::getField()} to build WHERE predicates and resolve
     * the required JOINs. Has no effect on row mapping, form mapping, ordering,
     * or the client payload.
     *
     * @see AbstractColumn::setSearchField()
     */
    public function getSearchField(): ?string;

    /**
     * Return the list of LEFT JOINs that must be applied before search predicates
     * are built for this column.
     *
     * Each entry is an associative array with keys:
     *   'join'          (string)  — e.g. 'e.donorProvider'
     *   'alias'         (string)  — e.g. 'dp'
     *   'conditionType' (?string) — Doctrine Join conditionType constant, or null
     *   'condition'     (?string) — DQL condition for the join, or null
     *
     * Applied by search filters before resolving predicates. Idempotent: a join
     * whose alias is already present on the QueryBuilder is skipped.
     *
     * @return list<array{join: string, alias: string, conditionType: ?string, condition: ?string}>
     */
    public function getSearchJoins(): array;
}
