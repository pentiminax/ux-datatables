<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Doctrine\ORM\QueryBuilder;
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
    protected ?string $searchField       = null;
    protected bool $globalSearchable     = true;
    protected bool $columnControlEnabled = true;
    protected ?array $columnControl      = null;
    protected array $customOptions       = [];
    protected ?string $permission        = null;
    protected ?int $responsivePriority   = null;

    /** @var list<array{join: string, alias: string, conditionType: ?string, condition: ?string}> */
    protected array $searchJoins = [];

    /** @var (\Closure(QueryBuilder, string, string, string): (string|null))|null */
    protected ?\Closure $searchPredicate = null;

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

    /**
     * Search this column against a different field path than the one it displays.
     *
     * Only search reads it -- global search, per-column search, and column control search. Row
     * mapping, form mapping, ordering, and the client payload keep using {@see setField()}.
     * Use it when the displayed value is assembled in mapRow() but the searchable data lives in
     * a mapped column or a relation:
     *
     *     TextColumn::new('donorProviderName')
     *         ->setSearchField('donorProvider.name');
     *
     * The path follows setField()'s dot-notation: a simple field is prefixed with the root
     * alias, a relation path adds a LEFT JOIN. Reach for {@see addSearchJoin()} instead when
     * the join needs a specific alias or a WITH condition.
     */
    public function setSearchField(string $searchField): static
    {
        $this->searchField = $searchField;

        return $this;
    }

    public function getSearchField(): ?string
    {
        return $this->searchField;
    }

    /**
     * Declare a LEFT JOIN to apply before this column's search predicate is built.
     *
     * Each call appends one join. A join whose alias is already on the QueryBuilder is skipped,
     * so declaring a relation customizeQueryBuilder() already joined under that alias is safe.
     * Use it to pick the alias yourself rather than accept the one setSearchField() derives:
     *
     *     TextColumn::new('donorProviderName')
     *         ->addSearchJoin('e.donorProvider', 'dp')
     *         ->setSearchField('dp.name');
     *
     * $conditionType and $condition are passed through to QueryBuilder::leftJoin() and must be
     * given together; either one alone is ignored.
     */
    public function addSearchJoin(
        string $join,
        string $alias,
        ?string $conditionType = null,
        ?string $condition = null,
    ): static {
        $this->searchJoins[] = [
            'join'          => $join,
            'alias'         => $alias,
            'conditionType' => $conditionType,
            'condition'     => $condition,
        ];

        return $this;
    }

    public function getSearchJoins(): array
    {
        return $this->searchJoins;
    }

    /**
     * Build this column's search condition yourself, for what the type-based LIKE and equality
     * predicates cannot express -- matching several fields at once, an EXISTS subquery, a
     * database function.
     *
     * The closure receives the QueryBuilder, the root alias, the raw search term, and a
     * parameter name unique to this column and search. Bind parameters on the QueryBuilder --
     * under $paramName or names derived from it -- and return a DQL condition, or null to skip
     * the column for that term. Do not call andWhere(): global search OR's the returned
     * conditions together, a column search AND's them.
     *
     *     TextColumn::new('donorProviderName')
     *         ->addSearchJoin('e.donorProvider', 'dp')
     *         ->setSearchPredicate(function (QueryBuilder $qb, string $alias, string $value, string $paramName): string {
     *             $qb->setParameter($paramName, '%'.$value.'%');
     *
     *             return "dp.name LIKE :{$paramName} OR dp.legalName LIKE :{$paramName}";
     *         });
     *
     * Overriding {@see buildSearchPredicate()} in a column subclass is the class-level
     * equivalent.
     *
     * @param \Closure(QueryBuilder, string, string, string): (string|null) $predicate
     */
    public function setSearchPredicate(\Closure $predicate): static
    {
        $this->searchPredicate = $predicate;

        return $this;
    }

    public function buildSearchPredicate(
        QueryBuilder $qb,
        string $alias,
        string $value,
        string $paramName,
    ): ?string {
        if (null === $this->searchPredicate) {
            return null;
        }

        return ($this->searchPredicate)($qb, $alias, $value, $paramName);
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
