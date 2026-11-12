<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\NormalizedSearchColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Model\Summary\Summarizer;
use Symfony\Component\ExpressionLanguage\Expression;

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
abstract class AbstractColumn implements SearchableColumnInterface, NormalizedSearchColumnInterface
{
    protected ColumnType $type;
    protected ?string $cellType                  = null;
    protected ?string $className                 = null;
    protected ?string $name                      = null;
    protected ?string $width                     = null;
    protected ?string $title                     = null;
    protected bool $orderable                    = true;
    protected bool $searchable                   = true;
    protected bool $visible                      = true;
    protected ?string $data                      = null;
    protected bool $exportable                   = true;
    protected ?string $defaultContent            = null;
    protected ?string $field                     = null;
    protected ?string $orderExpression           = null;
    protected ?string $searchField               = null;
    protected bool $globalSearchable             = true;
    protected bool $columnControlEnabled         = true;
    protected ?array $columnControl              = null;
    protected array $customOptions               = [];
    protected string|Expression|null $permission = null;
    protected ?int $responsivePriority           = null;
    protected bool $searchNormalized             = true;

    /** @var list<array{join: string, alias: string, conditionType: ?string, condition: ?string}> */
    protected array $searchJoins = [];

    /** @var (\Closure(QueryBuilder, string, string, string): (string|null))|null */
    protected ?\Closure $searchPredicate = null;

    /** @var (\Closure(mixed, mixed): mixed)|null */
    protected ?\Closure $valueFormatter = null;

    /** @var array<string, Summarizer> */
    protected array $summarizers = [];

    /**
     * Convenient factory helper used by concrete columns to set their type.
     *
     * @internal
     */
    protected static function createWithType(string $name, string $title, ColumnType $type): static
    {
        $resolvedTitle = '' === $title ? $name : $title;

        return (new static())
            ->data($name)
            ->name($name)
            ->title($resolvedTitle)
            ->type($type);
    }

    public function className(?string $className): static
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @deprecated since 1.6, use className() instead. Removed in 2.0.
     */
    public function setClassName(?string $className): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "className()" instead.', __METHOD__);

        return $this->className($className);
    }

    /**
     * Change the type of HTML cell created for this column (either "td" or "th").
     */
    public function cellType(?string $cellType): static
    {
        $this->cellType = $cellType;

        return $this;
    }

    /**
     * @deprecated since 1.6, use cellType() instead. Removed in 2.0.
     */
    public function setCellType(?string $cellType): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "cellType()" instead.', __METHOD__);

        return $this->cellType($cellType);
    }

    public function name(string $name): static
    {
        $this->name = $name;

        if (null === $this->title) {
            $this->title = $name;
        }

        return $this;
    }

    /**
     * @deprecated since 1.6, use name() instead. Removed in 2.0.
     */
    public function setName(string $name): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "name()" instead.', __METHOD__);

        return $this->name($name);
    }

    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * Enable or disable ordering on this column.
     */
    public function orderable(bool $orderable = true): static
    {
        $this->orderable = $orderable;

        return $this;
    }

    /**
     * @deprecated since 1.6, use orderable() instead. Removed in 2.0.
     */
    public function setOrderable(bool $orderable = true): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "orderable()" instead.', __METHOD__);

        return $this->orderable($orderable);
    }

    /**
     * Enable or disable searching on this column.
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * @deprecated since 1.6, use searchable() instead. Removed in 2.0.
     */
    public function setSearchable(bool $searchable = true): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "searchable()" instead.', __METHOD__);

        return $this->searchable($searchable);
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Whether server-side LIKE searches on this column lowercase both sides.
     *
     * Normalized (the default), a search behaves the same on every platform, but LOWER() on the
     * column prevents the database from using a plain index on it. Pass false when a "starts
     * with" search must stay sargable on a MySQL prefix index and the stored values already have
     * a known case. The bare LIKE then follows the column's collation: case-sensitive on
     * PostgreSQL and binary collations, still case-insensitive on a `_ci` MySQL collation.
     */
    public function searchNormalization(bool $normalized = true): static
    {
        $this->searchNormalized = $normalized;

        return $this;
    }

    /**
     * @deprecated since 1.6, use searchNormalization() instead. Removed in 2.0.
     */
    public function setSearchNormalization(bool $normalized = true): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "searchNormalization()" instead.', __METHOD__);

        return $this->searchNormalization($normalized);
    }

    public function isSearchNormalized(): bool
    {
        return $this->searchNormalized;
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
    public function columnControl(array $columnControl): static
    {
        $this->columnControl = $columnControl;

        return $this;
    }

    /**
     * @deprecated since 1.6, use columnControl() instead. Removed in 2.0.
     */
    public function setColumnControl(array $columnControl): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "columnControl()" instead.', __METHOD__);

        return $this->columnControl($columnControl);
    }

    public function getColumnControl(): ?array
    {
        return $this->columnControl;
    }

    /**
     * Set the column type (used for filtering and sorting string processing).
     */
    public function type(ColumnType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @deprecated since 1.6, use type() instead. Removed in 2.0.
     */
    public function setType(ColumnType $type): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "type()" instead.', __METHOD__);

        return $this->type($type);
    }

    /**
     * Define the width of this column (any valid CSS unit such as "120px", "3em", "20%").
     */
    public function width(?string $width): static
    {
        $this->width = $width;

        return $this;
    }

    /**
     * @deprecated since 1.6, use width() instead. Removed in 2.0.
     */
    public function setWidth(?string $width): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "width()" instead.', __METHOD__);

        return $this->width($width);
    }

    /**
     * Set the DataTables Responsive visibility priority for this column.
     *
     * Lower numbers stay visible longer. Omitted (null) lets Responsive use its default of 10000.
     * Negative values are allowed and increase priority further. Has a visible effect only when
     * the Responsive extension is enabled on the table.
     */
    public function responsivePriority(?int $priority): static
    {
        $this->responsivePriority = $priority;

        return $this;
    }

    /**
     * @deprecated since 1.6, use responsivePriority() instead. Removed in 2.0.
     */
    public function setResponsivePriority(?int $priority): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "responsivePriority()" instead.', __METHOD__);

        return $this->responsivePriority($priority);
    }

    public function getResponsivePriority(): ?int
    {
        return $this->responsivePriority;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @deprecated since 1.6, use title() instead. Removed in 2.0.
     */
    public function setTitle(string $title): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "title()" instead.', __METHOD__);

        return $this->title($title);
    }

    /**
     * Control whether this column is visible in the table.
     */
    public function visible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * @deprecated since 1.6, use visible() instead. Removed in 2.0.
     */
    public function setVisible(bool $visible): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "visible()" instead.', __METHOD__);

        return $this->visible($visible);
    }

    /**
     * Set the data source for this column (e.g. "user.email").
     */
    public function data(?string $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @deprecated since 1.6, use data() instead. Removed in 2.0.
     */
    public function setData(?string $data): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "data()" instead.', __METHOD__);

        return $this->data($data);
    }

    /**
     * Define a fallback content when the data source is null or missing.
     */
    public function defaultContent(?string $defaultContent): static
    {
        $this->defaultContent = $defaultContent;

        return $this;
    }

    /**
     * @deprecated since 1.6, use defaultContent() instead. Removed in 2.0.
     */
    public function setDefaultContent(?string $defaultContent): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "defaultContent()" instead.', __METHOD__);

        return $this->defaultContent($defaultContent);
    }

    /**
     * Enable or disable export for this column.
     */
    public function exportable(bool $exportable): static
    {
        $this->exportable = $exportable;

        return $this;
    }

    /**
     * @deprecated since 1.6, use exportable() instead. Removed in 2.0.
     */
    public function setExportable(bool $exportable): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "exportable()" instead.', __METHOD__);

        return $this->exportable($exportable);
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

    public function field(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    /**
     * @deprecated since 1.6, use field() instead. Removed in 2.0.
     */
    public function setField(string $field): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "field()" instead.', __METHOD__);

        return $this->field($field);
    }

    /**
     * Override the ORDER BY expression for this column (raw DQL or a SELECT alias).
     *
     * Bypasses the default "<alias>.<field>" resolution — use for computed columns
     * backed by an addSelect(... AS HIDDEN alias) in customizeQueryBuilder().
     */
    public function orderExpression(string $orderExpression): static
    {
        $this->orderExpression = $orderExpression;

        return $this;
    }

    /**
     * @deprecated since 1.6, use orderExpression() instead. Removed in 2.0.
     */
    public function setOrderExpression(string $orderExpression): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "orderExpression()" instead.', __METHOD__);

        return $this->orderExpression($orderExpression);
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
     *         ->searchField('donorProvider.name');
     *
     * The path follows setField()'s dot-notation: a simple field is prefixed with the root
     * alias, a relation path adds a LEFT JOIN. Reach for {@see addSearchJoin()} instead when
     * the join needs a specific alias or a WITH condition.
     */
    public function searchField(string $searchField): static
    {
        $this->searchField = $searchField;

        return $this;
    }

    /**
     * @deprecated since 1.6, use searchField() instead. Removed in 2.0.
     */
    public function setSearchField(string $searchField): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "searchField()" instead.', __METHOD__);

        return $this->searchField($searchField);
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
     *         ->searchField('dp.name');
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
     *         ->searchPredicate(function (QueryBuilder $qb, string $alias, string $value, string $paramName): string {
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
    public function searchPredicate(\Closure $predicate): static
    {
        $this->searchPredicate = $predicate;

        return $this;
    }

    /**
     * @deprecated since 1.6, use searchPredicate() instead. Removed in 2.0.
     */
    public function setSearchPredicate(\Closure $predicate): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "searchPredicate()" instead.', __METHOD__);

        return $this->searchPredicate($predicate);
    }

    /**
     * Whether a searchPredicate() closure replaces the type-based search condition.
     */
    public function hasSearchPredicate(): bool
    {
        return null !== $this->searchPredicate;
    }

    /**
     * Transforms the cell value on the server, before it reaches the browser or an export file.
     *
     * The closure receives the value after normalization (dates already formatted, enums reduced to
     * their backing value) and the source item, the same one actions receive. A text cell escapes
     * what the closure returns unless html() is set, so markup must be escaped by the closure.
     * A server-side table still orders and searches on the field; a client-side one on the
     * formatted value. The closure is never serialized.
     *
     * @param \Closure(mixed $value, mixed $row): mixed $formatter
     */
    public function formatValueUsing(\Closure $formatter): static
    {
        $this->valueFormatter = $formatter;

        return $this->customOption('formatted', true);
    }

    /**
     * @return (\Closure(mixed $value, mixed $row): mixed)|null
     */
    public function getValueFormatter(): ?\Closure
    {
        return $this->valueFormatter;
    }

    /**
     * Shows footer figures over every row matching the search and filters. Only Count accepts a
     * non-numeric column.
     *
     * @throws \InvalidArgumentException when the column cannot be summarized
     */
    public function summarize(Summarizer ...$summarizers): static
    {
        if (!$this->isSummarizable()) {
            throw new \InvalidArgumentException(\sprintf('The column "%s" cannot be summarized because it renders no data field.', $this->getName()));
        }

        foreach ($summarizers as $summarizer) {
            $type = $summarizer->getType();

            if ($type->requiresNumericColumn() && !$this->isNumber()) {
                throw new \InvalidArgumentException(\sprintf('The "%s" summary requires a numeric column, but "%s" is not numeric.', $type->value, $this->getName()));
            }

            $this->summarizers[$type->value] = $summarizer;
        }

        return $this;
    }

    /**
     * @return list<Summarizer>
     */
    public function getSummarizers(): array
    {
        return array_values($this->summarizers);
    }

    protected function isSummarizable(): bool
    {
        return true;
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

    public function customOption(string $optionName, mixed $optionValue): static
    {
        $this->customOptions[$optionName] = $optionValue;

        return $this;
    }

    /**
     * @deprecated since 1.6, use customOption() instead. Removed in 2.0.
     */
    public function setCustomOption(string $optionName, mixed $optionValue): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "customOption()" instead.', __METHOD__);

        return $this->customOption($optionName, $optionValue);
    }

    public function getCustomOption(string $optionName): mixed
    {
        return $this->customOptions[$optionName] ?? null;
    }

    /**
     * Restrict the column visibility with a Symfony security attribute or expression.
     *
     * Evaluated once before serialization. The attribute name is never sent to the client.
     */
    public function permission(string|Expression $attribute): static
    {
        $this->permission = $attribute;

        return $this;
    }

    /**
     * @deprecated since 1.6, use permission() instead. Removed in 2.0.
     */
    public function setPermission(string|Expression $attribute): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "permission()" instead.', __METHOD__);

        return $this->permission($attribute);
    }

    public function getPermission(): string|Expression|null
    {
        return $this->permission;
    }

    public function jsonSerialize(): array
    {
        $className = $this->className;

        if ($this->exportable) {
            $className = trim(\sprintf('%s dt-exportable', $className ?? '')) ?: null;
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
            'summaries'          => array_map(
                static fn (Summarizer $summarizer): array => array_filter(
                    ['type' => $summarizer->getType()->value, 'label' => $summarizer->getLabel()],
                    static fn (?string $value): bool => null !== $value,
                ),
                $this->getSummarizers(),
            ),
        ], static fn (mixed $value, string $key) => null !== $value && [] !== $value && ('' !== $value || 'defaultContent' === $key), ARRAY_FILTER_USE_BOTH);

        if (null !== $this->columnControl) {
            $options['columnControl'] = $this->columnControl;
        } elseif (!$this->columnControlEnabled) {
            $options['columnControl'] = [];
        }

        return $options;
    }
}
