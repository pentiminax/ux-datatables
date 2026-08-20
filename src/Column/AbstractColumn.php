<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\PermissionAwareColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchAwareColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;

/**
 * @internal
 */
abstract class AbstractColumn implements ColumnInterface, PermissionAwareColumnInterface, SearchableColumnInterface, SearchAwareColumnInterface
{
    protected ColumnType $type;
    protected ?string $cellType        = null;
    protected ?string $className       = null;
    protected ?string $name            = null;
    protected ?string $width           = null;
    protected ?string $title           = null;
    protected bool $orderable          = true;
    protected bool $searchable         = true;
    protected bool $visible            = true;
    protected ?string $data            = null;
    protected bool $exportable         = true;
    protected ?string $defaultContent  = null;
    protected ?string $field           = null;
    protected ?string $orderExpression = null;
    protected ?string $searchField     = null;
    /** @var list<array{join: string, alias: string, conditionType: ?string, condition: ?string}> */
    protected array $searchJoins = [];
    /** @var (\Closure(QueryBuilder, string, string, string): string|null)|null */
    protected ?\Closure $searchPredicate = null;
    protected bool $globalSearchable     = true;
    protected bool $columnControlEnabled = true;
    protected array $customOptions       = [];
    protected ?string $permission        = null;

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
     * Set the dot-notation field path used exclusively for search resolution.
     *
     * Affects search filters and strategies only (global search, per-column search,
     * and column control search). Has no effect on row mapping, form mapping,
     * ordering, or the serialized client payload.
     *
     * The path follows the same dot-notation convention as {@see setField()}:
     * simple fields ("name") are prefixed with the root alias; relation paths
     * ("donorProvider.name") add an automatic LEFT JOIN before building the predicate.
     *
     * Use {@see addSearchJoin()} when you need a specific alias, a conditional join,
     * or when you have already joined the relation under a custom alias in
     * {@see customizeQueryBuilder()}.
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
     * Declare a LEFT JOIN that must be applied before search predicates for this
     * column are built.
     *
     * Each call appends one join descriptor. Applied by search filters before
     * resolving the field or invoking the search predicate. Idempotent: a join
     * whose alias is already present on the QueryBuilder is silently skipped.
     *
     * Useful when you need a specific alias (e.g. 'dp' instead of 'donorProvider'),
     * a WITH condition, or when you want all search configuration expressed on the
     * column rather than in customizeQueryBuilder().
     *
     * Example:
     *
     *   TextColumn::new('donorProviderName')
     *       ->addSearchJoin('e.donorProvider', 'dp')
     *       ->setSearchField('dp.name');
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

    /**
     * @return list<array{join: string, alias: string, conditionType: ?string, condition: ?string}>
     */
    public function getSearchJoins(): array
    {
        return $this->searchJoins;
    }

    /**
     * Set a custom search predicate closure for this column.
     *
     * The closure receives the QueryBuilder, root alias, search value, and a
     * unique parameter name. It must bind its own parameters on the QueryBuilder
     * and return either a DQL fragment string or null to skip this column.
     *
     * The filter that calls this method decides how the fragment is composed:
     * global search OR's fragments from all searchable columns; per-column search
     * AND's each fragment.
     *
     * Example (search across two related fields):
     *
     *   TextColumn::new('donorProviderName')
     *       ->setSearchPredicate(
     *           function (QueryBuilder $qb, string $alias, string $value, string $paramName): ?string {
     *               $qb->setParameter($paramName.'_n', '%'.$value.'%');
     *               $qb->setParameter($paramName.'_l', '%'.$value.'%');
     *               return "dp.name LIKE :{$paramName}_n OR dp.legalName LIKE :{$paramName}_l";
     *           }
     *       );
     *
     * @param \Closure(QueryBuilder, string, string, string): (string|null) $predicate
     */
    public function setSearchPredicate(\Closure $predicate): static
    {
        $this->searchPredicate = $predicate;

        return $this;
    }

    /**
     * Build the DQL search predicate for this column.
     *
     * Invoked by search filters when the column has a custom predicate set via
     * {@see setSearchPredicate()} or when a subclass overrides this method.
     * Returns null by default (no custom predicate), allowing the standard
     * field-based resolution to take over.
     *
     * Subclasses may override this method directly as an alternative to calling
     * {@see setSearchPredicate()} on an instance.
     */
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
            'cellType'       => $this->cellType,
            'className'      => $className,
            'data'           => $this->data,
            'defaultContent' => $this->defaultContent,
            'name'           => $this->name,
            'orderable'      => $this->orderable,
            'searchable'     => $this->searchable,
            'title'          => $this->title,
            'type'           => $this->type->value,
            'visible'        => $this->visible,
            'width'          => $this->width,
            'field'          => $this->getField(),
            'customOptions'  => $this->customOptions,
        ], static fn (mixed $value) => null !== $value && '' !== $value && [] !== $value);

        if (!$this->columnControlEnabled) {
            $options['columnControl'] = [];
        }

        return $options;
    }
}
