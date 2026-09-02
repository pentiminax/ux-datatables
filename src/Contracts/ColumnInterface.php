<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Enum\ColumnType;

/**
 * One column of a table's configuration: the JSON handed to DataTables.net, the entity field the
 * server reads, and the flags the query and rendering boundaries consult.
 *
 * Implementations must behave as immutable-once-configured value objects. Every accessor is called
 * while AbstractDataTable resolves its columns -- on a container-shared instance -- and again for
 * each row, so none of them may read the request, the security token, or the locale.
 * getPermission() only names the required security attribute; ColumnResolver evaluates it per
 * request at the serialization and query boundaries.
 *
 * Extend {@see \Pentiminax\UX\DataTables\Column\AbstractColumn} instead of implementing this
 * directly: it provides the fluent setters, the ColumnType handling, and jsonSerialize(). Add
 * {@see TemplateAwareColumnInterface} or {@see ActionsProvidingColumnInterface} on top when the
 * column renders Twig or contributes row actions.
 */
interface ColumnInterface extends \JsonSerializable
{
    public function getName(): string;

    public function getField(): ?string;

    public function setField(string $field): static;

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

    /**
     * Security attribute required to see this column, or null when it is always visible.
     */
    public function getPermission(): ?string;
}
