<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Enum\ColumnType;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * One column of a table's configuration: the JSON handed to DataTables.net, the entity field the
 * server reads, and the flags the query and rendering boundaries consult.
 *
 * Implementations must behave as immutable-once-configured value objects. Every accessor is called
 * while AbstractDataTable resolves its columns -- on a container-shared instance -- and again for
 * each row, so none of them may read the request, the security token, or the locale.
 * getPermission() only names the required security attribute or expression; ColumnResolver
 * evaluates it per request at the serialization and query boundaries.
 *
 * Custom columns must extend a bundled column or {@see \Pentiminax\UX\DataTables\Column\AbstractColumn}:
 * summaries, value formatting, and search features read AbstractColumn, and a table deprecates any
 * other column since 1.6. This interface stays as the read-only view handed to extension points such
 * as search strategies. Add {@see TemplateAwareColumnInterface} or {@see ActionsProvidingColumnInterface}
 * on top of AbstractColumn when the column renders Twig or contributes row actions.
 */
interface ColumnInterface extends \JsonSerializable
{
    public function getName(): string;

    public function getField(): ?string;

    /**
     * @deprecated since 1.6, use AbstractColumn::field() instead. Removed in 2.0.
     */
    public function setField(string $field): static;

    public function getOrderExpression(): ?string;

    /**
     * @deprecated since 1.6, use AbstractColumn::visible() instead. Removed in 2.0.
     */
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

    public function getClassName(): ?string;

    public function getCustomOption(string $optionName): mixed;

    public function getCustomOptions(): array;

    /**
     * Security attribute or expression required to see this column, or null when it is always visible.
     */
    public function getPermission(): string|Expression|null;
}
