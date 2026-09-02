<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Enum\ColumnType;

/**
 * One column of a table's configuration: the JSON handed to DataTables.net, the entity field the
 * server reads, and the flags the query and rendering boundaries consult.
 *
 * Implementations must behave as immutable-once-configured value objects. Every accessor is called
 * while AbstractDataTable resolves its columns -- on a container-shared instance -- and again for
 * each row, so none of them may read the request, the security token, or the locale.
 * getPermission() only names the required security attribute; ColumnResolver evaluates it per
 * request at the serialization and query boundaries. buildSearchPredicate() is the one member
 * that is not an accessor: it runs only at the Doctrine query boundary, once per search term,
 * and is the only place a column may touch a QueryBuilder.
 *
 * Extend {@see \Pentiminax\UX\DataTables\Column\AbstractColumn} instead of implementing this
 * directly: it provides the fluent setters, the ColumnType handling, and jsonSerialize(). Add
 * {@see TemplateAwareColumnInterface} or {@see ActionsProvidingColumnInterface} on top when the
 * column renders Twig or contributes row actions.
 *
 * While the bundle is on v0.x this interface takes new members rather than growing a parallel
 * opt-in contract beside it, so a class implementing it directly can need changes on a minor
 * bump. UPGRADE.md carries the inert implementation for each addition.
 */
interface ColumnInterface extends \JsonSerializable
{
    public function getName(): string;

    public function getField(): ?string;

    public function setField(string $field): static;

    public function getOrderExpression(): ?string;

    /**
     * Field path used for search predicates instead of {@see self::getField()}, or null to
     * search the display field.
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
     * already joined under a custom alias in customizeQueryBuilder(). Joins whose alias is
     * already on the QueryBuilder are skipped, so returning the same list twice is harmless.
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

    public function setVisible(bool $visible): static;

    public function isSearchable(): bool;

    public function isGlobalSearchable(): bool;

    public function getData(): ?string;

    public function getTitle(): ?string;

    public function isNumber(): bool;

    public function isDate(): bool;

    public function getType(): ColumnType;

    public function isVisible(): bool;

    public function isOrderable(): bool;

    public function isExportable(): bool;

    public function getWidth(): ?string;

    public function getClassName(): ?string;

    public function getCellType(): ?string;

    public function getDefaultContent(): ?string;

    public function getCustomOption(string $optionName): mixed;

    public function getCustomOptions(): array;

    /**
     * Security attribute required to see this column, or null when it is always visible.
     */
    public function getPermission(): ?string;
}
