<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column as RequestColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Columns as RequestColumns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterChain;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractDataTable
{
    /**
     * @var array<class-string, AsDataTable|null>
     */
    private static array $attributeCache = [];

    protected DataTable $table;

    /**
     * @var ColumnInterface[]
     */
    private array $columns;

    private Filters $filters;

    private ?RowMapperInterface $defaultRowMapper = null;

    private ?AsDataTable $asDataTable = null;

    private ?DataTableInfrastructure $infrastructure = null;

    private ?DataTableRuntime $runtime = null;

    private bool $initialized = false;

    private bool $renderingPrepared = false;

    public function __construct()
    {
    }

    final public function setDataTableInfrastructure(DataTableInfrastructure $infrastructure): void
    {
        if ($this->initialized || null !== $this->runtime) {
            throw new \LogicException('DataTable infrastructure must be injected before the table is initialized.');
        }

        $this->infrastructure = $infrastructure;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->asDataTable = $this->resolveAsDataTable();

        $this->table = $this->configureDataTable(
            new DataTable($this->getClassName())
        );

        $this->table->setDataTableClass(static::class);

        $this->columns = iterator_to_array($this->configureColumns());

        $columnResolver = $this->infrastructure()->columnResolver();

        $columnResolver->configureBooleanColumns($this->columns, $this->asDataTable);

        $actions = $this->configureActions(new Actions());

        $columnResolver->configureActionEntityClass($actions, $this->asDataTable);
        $columnResolver->filterActionsByStaticPermissions($actions);

        $this->configureActionColumn($actions);

        $this->columns = $columnResolver->filterStaticPermissions($this->columns);

        $this->table->columns($this->columns);

        $this->filters = $this->configureFilters(new Filters());
        $this->table->setFilters($this->filters);

        $this->table->setExtensions(
            $this->configureExtensions(new DataTableExtensions())
        );

        $this->initialized = true;
    }

    public function getRequest(): ?DataTableRequest
    {
        return $this->runtime()->getRequest();
    }

    protected function getHttpRequest(): ?Request
    {
        return $this->runtime()->getHttpRequest();
    }

    public function handleRequest(Request $request): static
    {
        $this->runtime()->handleRequest($request);

        return $this;
    }

    public function isRequestHandled(): bool
    {
        return $this->runtime()->isRequestHandled();
    }

    public function getResponse(): JsonResponse
    {
        return $this->runtime()->getResponse();
    }

    public function prepareForRendering(): void
    {
        $this->initialize();

        if ($this->renderingPrepared) {
            return;
        }

        $renderingPreparer = $this->infrastructure()->renderingPreparer();

        $renderingPreparer->prepareBeforeDataHydration($this->table, $this->asDataTable);
        $this->prepareExplicitInlineData();
        $this->hydrateClientSideData();
        $renderingPreparer->prepareAfterDataHydration($this->table, $this->asDataTable);

        $this->renderingPrepared = true;
    }

    public function getDataTable(): DataTable
    {
        $this->prepareForRendering();

        return $this->table;
    }

    public function getConfiguredDataTable(): DataTable
    {
        $this->initialize();

        return $this->table;
    }

    /**
     * Resolve the Mercure configuration exactly as the render path would
     * serialize it to the browser, WITHOUT hydrating client-side data and
     * WITHOUT mutating this container-shared instance.
     *
     * Delegates to the same pure RenderingPreparer::resolveMercureConfig() the
     * render path uses, so published topics always match the ones the client
     * subscribed to, but deliberately skips hydrateClientSideData() so resolving
     * topics for a mutation never triggers a data-provider / DB query as a side
     * effect. When the render path would embed static client-side data — which
     * disables Mercure live updates — this returns null, mirroring
     * RenderingPreparer::configureMercure().
     *
     * Callers that must not fail (e.g. after a committed mutation) should guard
     * against the LogicException that Mercure hub-URL resolution can throw.
     *
     * @throws \LogicException if Mercure is enabled but the hub URL cannot be resolved
     */
    public function resolveMercureConfigWithoutHydration(): ?MercureConfig
    {
        $this->initialize();

        // A client-side table embeds its rows at render time, and
        // configureMercure() suppresses attribute/auto-resolved live updates once
        // inline data is present. Manual ->mercure() config is always serialized,
        // so only short-circuit when there is no manual config to preserve.
        // Reproduce that suppression here WITHOUT performing the data fetch.
        if (null === $this->table->getMercureConfig() && $this->shouldHydrateClientSideData()) {
            return null;
        }

        return $this->infrastructure()
            ->renderingPreparer()
            ->resolveMercureConfig($this->table, $this->asDataTable);
    }

    final public function getEntityClass(): ?string
    {
        $this->initialize();

        return $this->asDataTable?->entityClass;
    }

    /**
     * @return iterable<ColumnInterface>
     */
    public function configureColumns(): iterable
    {
        if (isset($this->table)) {
            $columns = $this->table->getColumns();
            if ([] !== $columns) {
                return $columns;
            }
        }

        return $this->infrastructure()->columnResolver()->resolveColumns($this->asDataTable ?? $this->resolveAsDataTable());
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table;
    }

    final public function getDataProvider(): ?DataProviderInterface
    {
        return $this->runtime()->getDataProvider();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions;
    }

    public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
    {
        return $extensions;
    }

    /**
     * Declare user-facing filters rendered above the table.
     *
     * Override to add TextFilter, ChoiceFilter, TernaryFilter, DateRangeFilter
     * or a generic Filter with a query() closure.
     */
    public function configureFilters(Filters $filters): Filters
    {
        return $filters;
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return $this->runtime()->fetchData($request);
    }

    private function hydrateClientSideData(): void
    {
        if (!$this->shouldHydrateClientSideData()) {
            return;
        }

        $this->fetchData($this->createClientSideDataRequest());
    }

    private function prepareExplicitInlineData(): void
    {
        $data = $this->table->getOption('data');
        if (null === $data || $this->table->areTemplateColumnsRendered()) {
            return;
        }

        $rowMapper = $this->createRowMapper();
        $rows      = [];

        foreach ($data as $item) {
            $rows[] = $rowMapper->map($item);
        }

        $this->table->data($rows);
        $this->table->markTemplateColumnsRendered();
    }

    private function shouldHydrateClientSideData(): bool
    {
        return !$this->table->isServerSide()
            && null === $this->table->getOption('data')
            && null === $this->table->getOption('ajax')
            && true !== $this->table->getOption('apiPlatform')
            && true !== $this->asDataTable?->apiPlatform;
    }

    private function createClientSideDataRequest(): DataTableRequest
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[$column->getName()] = new RequestColumn(
                data: $column->getData() ?? $column->getName(),
                name: $column->getName(),
                searchable: $column->isSearchable(),
                orderable: $column->isOrderable(),
            );
        }

        return new DataTableRequest(
            draw: null,
            columns: new RequestColumns($columns),
            start: 0,
            length: 0,
        );
    }

    final protected function configureQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $qb = $this->customizeQueryBuilder($qb, $request);

        $intent = $this->infrastructure()->queryIntentFactory()->create($request, array_values($this->columns));

        $columnsByName = [];
        foreach ($this->columns as $column) {
            $columnsByName[$column->getName()] = $column;
        }

        $context = new QueryFilterContext(
            intent: $intent,
            columns: $columnsByName,
            alias: 'e'
        );

        $registry = $this->createSearchStrategyRegistry();

        $qb = QueryFilterChain::createDefault($registry)->apply($qb, $context);

        $this->applyConfiguredFilters($qb, $request);

        return $qb;
    }

    private function applyConfiguredFilters(QueryBuilder $qb, DataTableRequest $request): void
    {
        if (!isset($this->filters) || $this->filters->isEmpty()) {
            return;
        }

        foreach ($this->filters->getFilters() as $filter) {
            $value = $request->filters[$filter->getName()] ?? null;

            if (null === $value || '' === $value || [] === $value) {
                continue;
            }

            $filter->apply($qb, $value, 'e');
        }
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        return $qb;
    }

    /**
     * Create and configure the search strategy registry.
     *
     * Override this method to register custom search strategies.
     */
    protected function createSearchStrategyRegistry(): SearchStrategyRegistry
    {
        return new DefaultSearchStrategyRegistry();
    }

    public function getColumnByName(string $name): ?ColumnInterface
    {
        $this->initialize();

        return $this->table->getColumnByName($name);
    }

    protected function mapRow(mixed $row): array
    {
        return $this->getDefaultRowMapper()->map($row);
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return null;
    }

    /**
     * Transform a complete, already-paginated page of source entities.
     *
     * Override to batch-enrich the page (load metrics, project to DTOs) without an N+1.
     * Return null (the default) to disable projection. When projecting, the returned list
     * must preserve the count and order of $items: columns and Twig then read the projected
     * item, while actions still receive the source entity.
     *
     * @param list<mixed> $items
     *
     * @return list<mixed>|null
     */
    protected function projectPage(array $items): ?array
    {
        return null;
    }

    final protected function createRowMapper(): RowMapperInterface
    {
        $this->initialize();

        return $this->infrastructure()->runtimeFactory()->createRowMapper(
            baseMapper: $this->mapRow(...),
            columns: $this->columns,
        );
    }

    public function setData(array $data): void
    {
        $this->initialize();

        $rowMapper = $this->createRowMapper();
        $rows      = [];

        foreach ($data as $item) {
            $rows[] = $rowMapper->map($item);
        }

        $this->table->data($rows);
        $this->table->markTemplateColumnsRendered();
    }

    private function getDefaultRowMapper(): DefaultRowMapper
    {
        $this->initialize();

        if (null === $this->defaultRowMapper) {
            $this->defaultRowMapper = new DefaultRowMapper($this->columns);
        }

        return $this->defaultRowMapper;
    }

    private function getClassName(): string
    {
        $class = static::class;
        $pos   = strrpos($class, '\\');

        return false === $pos ? $class : substr($class, $pos + 1);
    }

    private function resolveAsDataTable(): ?AsDataTable
    {
        $class = static::class;

        if (\array_key_exists($class, self::$attributeCache)) {
            return self::$attributeCache[$class];
        }

        $attributes = (new \ReflectionClass($class))->getAttributes(AsDataTable::class);

        return self::$attributeCache[$class] = [] === $attributes ? null : $attributes[0]->newInstance();
    }

    private function runtime(): DataTableRuntime
    {
        $this->initialize();

        return $this->runtime ??= $this->infrastructure()->runtimeFactory()->createRuntime(
            table: $this->table,
            columns: $this->columns,
            asDataTable: $this->asDataTable,
            baseMapper: $this->mapRow(...),
            manualDataProviderFactory: $this->createDataProvider(...),
            configureQueryBuilder: $this->configureQueryBuilder(...),
            pageProjector: $this->projectPage(...),
        );
    }

    private function infrastructure(): DataTableInfrastructure
    {
        return $this->infrastructure ??= DataTableInfrastructure::createDefault();
    }

    private function configureActionColumn(Actions $actions): void
    {
        if ($actions->isEmpty()) {
            return;
        }

        $groups = $actions->partitionByPosition();
        $single = 1 === \count($groups);

        foreach ($groups as $position => $group) {
            $isBefore = ActionsPosition::BeforeColumns->value === $position;

            $name = $single || !$isBefore ? 'actions' : 'actions_before';

            $actionColumn = $this->createActionColumn($name, $group);

            if ($isBefore) {
                array_unshift($this->columns, $actionColumn);

                continue;
            }

            $this->columns[] = $actionColumn;
        }
    }

    private function createActionColumn(string $name, Actions $actions): ActionColumn
    {
        $actionColumn = ActionColumn::fromActions(
            name: $name,
            title: $actions->getColumnLabel(),
            actions: $actions,
        );

        $className = trim(implode(' ', array_filter([
            $actions->getColumnClassName(),
            $actions->getAlignment()?->cssClass(),
        ])));

        if ('' !== $className) {
            $actionColumn->setClassName($className);
        }

        return $actionColumn;
    }
}
