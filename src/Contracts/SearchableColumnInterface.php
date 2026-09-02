<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;

/**
 * A column that describes how it is searched, independently of the field it displays.
 *
 * Server-side search resolves a plain {@see ColumnInterface} to "<alias>.<field>". A column whose
 * value is assembled in mapRow() has no such field, so it is skipped rather than emitted -- its
 * search box matches nothing. Implement this to point search somewhere valid: another field path,
 * a relation reached through a declared join, or a condition built by hand.
 *
 * {@see \Pentiminax\UX\DataTables\Column\AbstractColumn} implements all three members and exposes
 * them as setSearchField(), addSearchJoin(), and setSearchPredicate(), so every bundled column type
 * and any subclass of one already satisfies this contract. A column implementing ColumnInterface
 * directly is unaffected: search falls back to getField() with no extra joins.
 *
 * Unlike the rest of ColumnInterface, buildSearchPredicate() is not an accessor. It runs only at
 * the Doctrine query boundary, once per search term, and is the only place a column may touch a
 * QueryBuilder.
 */
interface SearchableColumnInterface extends ColumnInterface
{
    /**
     * Field path to search instead of {@see ColumnInterface::getField()}, or null to search the
     * displayed field.
     *
     * Search is the only boundary that reads this: row mapping, form mapping, ordering, and the
     * serialized client payload keep using getField(). Same dot-notation as getField(), so a
     * relation path is resolved through a LEFT JOIN.
     */
    public function getSearchField(): ?string;

    /**
     * LEFT JOINs to apply before this column's search predicate is built.
     *
     * Declare one when the search field needs a specific alias, a WITH condition, or a relation
     * already joined under a custom alias in customizeQueryBuilder(). Joins whose alias is already
     * on the QueryBuilder are skipped, so returning the same list twice is harmless.
     *
     * @return list<array{join: string, alias: string, conditionType: ?string, condition: ?string}>
     */
    public function getSearchJoins(): array;

    /**
     * Custom DQL search condition for $value, or null to fall back to the type-based predicate.
     *
     * Implementations bind their own parameters on $qb under names derived from $paramName and
     * return a condition rather than calling andWhere(): the caller composes it, with OR for
     * global search and AND for a column search. $alias is the root alias.
     */
    public function buildSearchPredicate(
        QueryBuilder $qb,
        string $alias,
        string $value,
        string $paramName,
    ): ?string;
}
