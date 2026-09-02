<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;

/**
 * Base implementation shared by every bundled column type (TextColumn, DateColumn, ...).
 *
 * Not meant to be extended directly to build a custom column type — extend a concrete column
 * class instead, or implement {@see ColumnInterface} directly for something that shares nothing
 * with the bundled types. Every public method here (setField(), setColumnControl(),
 * setClassName(), setCustomOption(), setVisible(), setResponsivePriority(),
 * disableGlobalSearch(), ...) is part of the
 * bundle's documented column-configuration API and is inherited unchanged by every concrete
 * column — see the "Columns" documentation for usage. Only createWithType() below is genuinely
 * internal, restricted to how the bundled column types build themselves.
 */
abstract class AbstractColumn implements ColumnInterface
{
    protected ColumnType $type;
    protected ?string $cellType          = null;
    protected ?string $className         = null;
    protected ?string $name              = null;
    protected ?string $width             = null;
    protected ?string $title             = null;
    protected bool $orderable            = true;
    protected bool $searchable           = true;
    protected bool $visible              = true;
    protected ?string $data              = null;
    protected bool $exportable           = true;
    protected ?string $defaultContent    = null;
    protected ?string $field             = null;
    protected ?string $orderExpression   = null;
    protected bool $globalSearchable     = true;
    protected bool $columnControlEnabled = true;
    protected ?array $columnControl      = null;
    protected array $customOptions       = [];
    protected ?string $permission        = null;
    protected ?int $responsivePriority   = null;

    /**
     * Convenient factory helper used by concrete columns to set their type.
     *
     * @internal
     */
    protected static function createWithType(string $name, string $title, ColumnType $type): static
    {
        $resolvedTitle = '' === $title ? $name : $title;

        return (new static())
            ->setData($name)
            ->setName($name)
            ->setTitle($resolvedTitle)
            ->setType($type);
    }

    public function setClassName(?string $className): static
    {
        $this->className = $className;

        return $this;
    }

    /**
     * Change the type of HTML cell created for this column (either "td" or "th").
     */
    public function setCellType(?string $cellType): static
    {
        $this->cellType = $cellType;

        return $this;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        if (null === $this->title) {
            $this->title = $name;
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * Enable or disable ordering on this column.
     */
    public function setOrderable(bool $orderable = true): static
    {
        $this->orderable = $orderable;

        return $this;
    }

    /**
     * Enable or disable searching on this column.
     */
    public function setSearchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isGlobalSearchable(): bool
    {
        return $this->globalSearchable;
    }

    public function disableGlobalSearch(): static
    {
        $this->globalSearchable = false;

        return $this;
    }

    /**
     * Disable all ColumnControl controls for this column.
     */
    public function disableColumnControl(): static
    {
        $this->columnControlEnabled = false;

        return $this;
    }

    /**
     * Override the ColumnControl content for this column only, bypassing the table-level
     * `ColumnControlExtension` default for it.
     *
     * Accepts the same content descriptors as the DataTables `columns.columnControl` option: an
     * array of content items (e.g. `['orderAsc', 'orderDesc']`), nested arrays for multiple
     * target areas, or extension descriptors such as `['colvisDropdown']`. Takes precedence over
     * disableColumnControl() regardless of call order.
     *
     * Has no visible effect unless `ColumnControlExtension` is also added to the table (e.g. via
     * `$table->columnControl()`) — the frontend only loads the ColumnControl plugin bundle when
     * the table-level extension is present, so this column-level content stays inert otherwise.
     *
     * @param list<mixed> $columnControl
     */
    public function setColumnControl(array $columnControl): static
    {
        $this->columnControl = $columnControl;

        return $this;
    }

    public function getColumnControl(): ?array
    {
        return $this->columnControl;
    }

    /**
     * Set the column type (used for filtering and sorting string processing).
     */
    public function setType(ColumnType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Define the width of this column (any valid CSS unit such as "120px", "3em", "20%").
     */
    public function setWidth(?string $width): static
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Set the DataTables Responsive visibility priority for this column.
     *
     * Lower numbers stay visible longer. Omitted (null) lets Responsive use its default of 10000.
     * Negative values are allowed and increase priority further. Has a visible effect only when
     * the Responsive extension is enabled on the table.
     */
    public function setResponsivePriority(?int $priority): static
    {
        $this->responsivePriority = $priority;

        return $this;
    }

    public function getResponsivePriority(): ?int
    {
        return $this->responsivePriority;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Control whether this column is visible in the table.
     */
    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Set the data source for this column (e.g. "user.email").
     */
    public function setData(?string $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Define a fallback content when the data source is null or missing.
     */
    public function setDefaultContent(?string $defaultContent): static
    {
        $this->defaultContent = $defaultContent;

        return $this;
    }

    /**
     * Enable or disable export for this column.
     */
    public function setExportable(bool $exportable): static
    {
        $this->exportable = $exportable;

        return $this;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function getType(): ColumnType
    {
        return $this->type;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function isOrderable(): bool
    {
        return $this->orderable;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getCellType(): ?string
    {
        return $this->cellType;
    }

    public function getDefaultContent(): ?string
    {
        return $this->defaultContent;
    }

    public function getCustomOptions(): array
    {
        return $this->customOptions;
    }

    public function isNumber(): bool
    {
        return $this->type->isNumber();
    }

    public function isDate(): bool
    {
        return $this->type->isDate();
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getField(): ?string
    {
        return $this->field ?? $this->name;
    }

    public function setField(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Override the ORDER BY expression for this column (raw DQL or a SELECT alias).
     *
     * Bypasses the default "<alias>.<field>" resolution — use for computed columns
     * backed by an addSelect(... AS HIDDEN alias) in customizeQueryBuilder().
     */
    public function setOrderExpression(string $orderExpression): static
    {
        $this->orderExpression = $orderExpression;

        return $this;
    }

    public function getOrderExpression(): ?string
    {
        return $this->orderExpression;
    }

    public function hideWhenUpdating(bool $hidden = true): static
    {
        $this->customOptions['hideWhenUpdating'] = $hidden;

        return $this;
    }

    public function setCustomOption(string $optionName, mixed $optionValue): static
    {
        $this->customOptions[$optionName] = $optionValue;

        return $this;
    }

    public function getCustomOption(string $optionName): mixed
    {
        return $this->customOptions[$optionName] ?? null;
    }

    /**
     * Restrict the column visibility with a Symfony security attribute (role, voter, expression).
     *
     * Evaluated once before serialization. The attribute name is never sent to the client.
     */
    public function permission(string $attribute): static
    {
        $this->permission = $attribute;

        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function jsonSerialize(): array
    {
        $className = $this->className;

        if (!$this->exportable) {
            $className = trim(\sprintf('%s not-exportable', $className ?? '')) ?: null;
        }

        $options = array_filter([
            'cellType'           => $this->cellType,
            'className'          => $className,
            'data'               => $this->data,
            'defaultContent'     => $this->defaultContent,
            'name'               => $this->name,
            'orderable'          => $this->orderable,
            'searchable'         => $this->searchable,
            'title'              => $this->title,
            'type'               => $this->type->value,
            'visible'            => $this->visible,
            'width'              => $this->width,
            'field'              => $this->getField(),
            'customOptions'      => $this->customOptions,
            'responsivePriority' => $this->responsivePriority,
        ], static fn (mixed $value) => null !== $value && '' !== $value && [] !== $value);

        if (null !== $this->columnControl) {
            $options['columnControl'] = $this->columnControl;
        } elseif (!$this->columnControlEnabled) {
            $options['columnControl'] = [];
        }

        return $options;
    }
}
