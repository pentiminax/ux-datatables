<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterChain;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractDataTable
{
    protected DataTable $table;

    /**
     * @var ColumnInterface[]
     */
    private array $columns;

    private ?RowMapperInterface $defaultRowMapper = null;

    private ?AsDataTable $asDataTable;

    private ColumnResolver $columnResolver;

    private RenderingPreparer $renderingPreparer;

    private DataTableRuntimeFactory $runtimeFactory;

    private ?DataTableRuntime $runtime = null;

    private bool $renderingPrepared = false;

    public function __construct(
        ?ColumnResolver $columnResolver = null,
        ?RenderingPreparer $renderingPreparer = null,
        ?DataTableRuntimeFactory $runtimeFactory = null,
    ) {
        $this->asDataTable       = $this->resolveAsDataTable();
        $this->columnResolver    = $columnResolver    ?? new ColumnResolver();
        $this->renderingPreparer = $renderingPreparer ?? new RenderingPreparer();
        $this->runtimeFactory    = $runtimeFactory    ?? new DataTableRuntimeFactory();

        $this->initializeTable();
        $this->initializeColumns();
        $this->initializeExtensions();
    }

    private function initializeTable(): void
    {
        $this->table = $this->configureDataTable(
            new DataTable($this->getClassName())
        );
    }

    private function initializeColumns(): void
    {
        $this->columns = iterator_to_array($this->configureColumns());

        $this->columnResolver->configureBooleanColumns($this->columns, $this->asDataTable);
        $this->columnResolver->configureUrlColumns($this->columns);

        $actions = $this->configureActions(new Actions());
        $this->columnResolver->configureActionEntityClass($actions, $this->asDataTable);

        if (!$actions->isEmpty()) {
            $this->columns[] = ActionColumn::fromActions(
                name: 'actions',
                title: $actions->getColumnLabel(),
                actions: $actions,
            );
        }

        $this->table->columns($this->columns);
    }

    private function initializeExtensions(): void
    {
        $this->table->setExtensions(
            $this->configureExtensions(new DataTableExtensions())
        );
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
        if ($this->renderingPrepared) {
            return;
        }

        $this->renderingPrepared = true;
        $this->renderingPreparer->prepare($this->table, $this->asDataTable);
    }

    public function getDataTable(): DataTable
    {
        $this->prepareForRendering();

        return $this->table;
    }

    /**
     * @return iterable<AbstractColumn>
     */
    public function configureColumns(): iterable
    {
        $columns = $this->table->getColumns();
        if ([] !== $columns) {
            return $columns;
        }

        return $this->columnResolver->resolveColumns($this->asDataTable);
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

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return $this->runtime()->fetchData($request);
    }

    public function queryBuilderConfigurator(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $context = new QueryFilterContext(
            request: $request,
            columns: $this->columns,
            alias: 'e'
        );

        $registry = $this->createSearchStrategyRegistry();

        return QueryFilterChain::createDefault($registry)->apply($qb, $context);
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

    public function setEntityManager(?EntityManagerInterface $em): void
    {
        $this->runtimeFactory->setEntityManager($em);
    }

    public function getColumnByName(string $name): ?ColumnInterface
    {
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

    final protected function createRowMapper(): RowMapperInterface
    {
        return $this->runtimeFactory->createRowMapper(
            baseMapper: $this->mapRow(...),
            columns: $this->columns,
        );
    }

    private function getDefaultRowMapper(): DefaultRowMapper
    {
        if (null === $this->defaultRowMapper) {
            $this->defaultRowMapper = new DefaultRowMapper($this->columns);
        }

        return $this->defaultRowMapper;
    }

    private function getClassName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    private function resolveAsDataTable(): ?AsDataTable
    {
        $attributes = (new \ReflectionClass($this))->getAttributes(AsDataTable::class);

        if ([] === $attributes) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private function runtime(): DataTableRuntime
    {
        return $this->runtime ??= $this->runtimeFactory->createRuntime(
            table: $this->table,
            columns: $this->columns,
            asDataTable: $this->asDataTable,
            baseMapper: $this->mapRow(...),
            manualDataProviderFactory: $this->createDataProvider(...),
            queryBuilderConfigurator: $this->queryBuilderConfigurator(...),
        );
    }
}
