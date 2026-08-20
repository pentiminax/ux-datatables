<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Enum\ColumnType;

interface ColumnInterface extends \JsonSerializable
{
    public function getName(): string;

    public function getField(): ?string;

    public function setField(string $field): static;

    /**
     * Return the dot-notation field path used exclusively for search resolution.
     *
     * When non-null, search filters and strategies use this path instead of
     * {@see getField()} to build WHERE predicates and resolve the required JOINs.
     * Has no effect on row mapping, form mapping, ordering, or the client payload.
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

    public function getOrderExpression(): ?string;

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
}
