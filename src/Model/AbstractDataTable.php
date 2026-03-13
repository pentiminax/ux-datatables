<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\UrlColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterChain;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\RowMapper\ClosureRowMapper;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractDataTable
{
    protected DataTable $table;

    protected ?DataTableRequest $request = null;

    /**
     * @var ColumnInterface[]
     */
    private array $columns;

    private ?DataProviderInterface $autoConfiguredProvider = null;

    private bool $providerAutoConfigured = false;

    private ?RowMapperInterface $rowMapper = null;

    private ?AsDataTable $asDataTable;

    private ColumnResolver $columnResolver;

    private RenderingPreparer $renderingPreparer;

    private bool $renderingPrepared = false;

    public function __construct(
        protected ?ColumnAutoDetectorInterface $columnAutoDetector = null,
        protected ?EntityManagerInterface $em = null,
        protected ?ApiResourceCollectionUrlResolverInterface $apiResourceCollectionUrlResolver = null,
        protected ?MercureConfigResolverInterface $mercureConfigResolver = null,
        protected ?AttributeColumnReader $attributeColumnReader = null,
        protected ?UrlColumnResolver $urlColumnResolver = null,
        protected ?TemplateColumnRenderer $templateColumnRenderer = null,
        protected ?ActionRowDataResolver $actionRowDataResolver = null,
    ) {
        $this->asDataTable       = $this->resolveAsDataTable();
        $this->columnResolver    = new ColumnResolver($attributeColumnReader, $columnAutoDetector, $urlColumnResolver);
        $this->renderingPreparer = new RenderingPreparer($apiResourceCollectionUrlResolver, $mercureConfigResolver);

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
        return $this->request;
    }

    public function handleRequest(Request $request): static
    {
        $this->request = DataTableRequest::fromRequest($request);

        return $this;
    }

    public function isRequestHandled(): bool
    {
        return null !== $this->request && $this->request->draw > 0;
    }

    public function getResponse(): JsonResponse
    {
        if (!$this->request) {
            return new JsonResponse([
                'draw'            => 1,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
            ]);
        }

        $provider = $this->getDataProvider();
        if (null === $provider) {
            return new JsonResponse([
                'draw'            => $this->request->draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
            ]);
        }

        $data = $provider->fetchData($this->request);

        return new JsonResponse([
            'draw'            => $this->request->draw,
            'recordsTotal'    => $data->recordsTotal,
            'recordsFiltered' => $data->recordsFiltered,
            'data'            => iterator_to_array($data->data),
        ]);
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

    public function getDataProvider(): ?DataProviderInterface
    {
        if ($this->providerAutoConfigured) {
            return $this->autoConfiguredProvider;
        }

        $this->providerAutoConfigured = true;

        $asDataTable = $this->asDataTable;
        if (null === $asDataTable) {
            return null;
        }

        if (null === $this->em) {
            throw new \LogicException('EntityManagerInterface is required to auto-configure a DoctrineDataProvider from #[AsDataTable]. Inject it via constructor or setEntityManager().');
        }

        $this->autoConfiguredProvider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: $asDataTable->entityClass,
            rowMapper: $this->rowMapper(),
            queryBuilderConfigurator: $this->queryBuilderConfigurator(...)
        );

        return $this->autoConfiguredProvider;
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
        if ($this->table->isServerSide()) {
            return $this->getDataProvider()?->fetchData($request);
        }

        $result = $this->getDataProvider()?->fetchData($request);
        $data   = iterator_to_array($result->data);

        $this->table->data($data);
        $this->table->markTemplateColumnsRendered();

        return $result;
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
        $this->em = $em;
    }

    public function setColumnAutoDetector(?ColumnAutoDetectorInterface $columnAutoDetector): void
    {
        $this->columnAutoDetector = $columnAutoDetector;
        $this->columnResolver->setColumnAutoDetector($columnAutoDetector);
    }

    /**
     * Build columns from #[Column] attributes on the entity class.
     *
     * @deprecated Override configureColumns() or use ColumnResolver directly
     *
     * @return AbstractColumn[]
     */
    protected function columnsFromAttributes(): array
    {
        return $this->columnResolver->columnsFromAttributes($this->asDataTable);
    }

    /**
     * Auto-detect columns from API Platform metadata.
     *
     * @deprecated Override configureColumns() or use ColumnResolver directly
     *
     * @param string[] $groups Serialization groups to filter properties (defaults to AsDataTable::$serializationGroups)
     *
     * @return AbstractColumn[]
     */
    protected function autoDetectColumns(array $groups = []): array
    {
        return $this->columnResolver->autoDetectColumns($this->asDataTable, $groups);
    }

    public function getColumnByName(string $name): ?ColumnInterface
    {
        return $this->table->getColumnByName($name);
    }

    protected function mapRow(mixed $row): array
    {
        return $this->getDefaultRowMapper()->map($row);
    }

    protected function rowMapper(): RowMapperInterface
    {
        return new ClosureRowMapper(
            function (mixed $row): array {
                $mappedRow = $this->mapRow($row);

                $mappedRow = $this->templateColumnRenderer?->renderRow(
                    row: $mappedRow,
                    mappedRow: $row,
                    columns: $this->columns
                ) ?? $mappedRow;

                return $this->actionRowDataResolver?->resolveRow($mappedRow, $row, $this->columns) ?? $mappedRow;
            }
        );
    }

    private function getDefaultRowMapper(): DefaultRowMapper
    {
        if (null === $this->rowMapper) {
            $this->rowMapper = new DefaultRowMapper($this->columns);
        }

        return $this->rowMapper;
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
}
