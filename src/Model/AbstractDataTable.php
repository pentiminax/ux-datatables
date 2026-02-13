<?php

namespace Pentiminax\UX\DataTables\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Builder\DataTableResponseBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterChain;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\RowMapper\ClosureRowMapper;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractDataTable implements DataTableInterface
{
    protected DataTable $table;

    protected ?DataTableRequest $request = null;

    /**
     * @var AbstractColumn[]
     */
    private array $columns;

    private ?DataProviderInterface $autoConfiguredProvider = null;

    private bool $providerAutoConfigured = false;

    private ?RowMapperInterface $rowMapper = null;

    private bool $asDataTableResolved = false;

    private ?AsDataTable $cachedAsDataTable = null;

    public function __construct(
        protected ?ColumnAutoDetectorInterface $columnAutoDetector = null,
        protected ?EntityManagerInterface $em = null,
        protected ?ApiResourceCollectionUrlResolverInterface $apiResourceCollectionUrlResolver = null,
        protected ?AttributeColumnReader $attributeColumnReader = null,
    ) {
        $this->table = $this->configureDataTable(
            new DataTable($this->getClassName())
        );

        $this->columns = iterator_to_array($this->configureColumns());

        $this->configureBooleanColumns();

        $this->table->columns($this->columns);

        $this->table->setExtensions(
            $this->configureExtensions(new DataTableExtensions())
        );

        $buttonsExtension = $this->configureButtonsExtension(new ButtonsExtension([]));
        if ($buttonsExtension->isEnabled()) {
            $this->table->addExtension($buttonsExtension);
        }

        $columnControlExtension = $this->configureColumnControlExtension(new ColumnControlExtension());
        if ($columnControlExtension->isEnabled()) {
            $this->table->addExtension($columnControlExtension);
        }

        $selectExtension = $this->configureSelectExtension(new SelectExtension());
        if ($selectExtension->isEnabled()) {
            $this->table->addExtension($selectExtension);
        }
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
                'draw'            => 0,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
            ]);
        }

        $data = $this->getDataProvider()?->fetchData($this->request);

        return (new DataTableResponseBuilder())
            ->buildResponse(
                draw: $this->request->draw,
                data: iterator_to_array($data->data),
                recordsTotal: $data->recordsTotal,
                recordsFiltered: $data->recordsFiltered
            );
    }

    public function prepareForRendering(): void
    {
        if (null !== $this->table->getOption('ajax')) {
            return;
        }

        if (null !== $this->table->getOption('data')) {
            return;
        }

        if (null === $this->apiResourceCollectionUrlResolver) {
            return;
        }

        $asDataTable = $this->getAsDataTableAttribute();
        if (null === $asDataTable) {
            return;
        }

        $collectionUrl = $this->apiResourceCollectionUrlResolver->resolveCollectionUrl($asDataTable->entityClass);

        if (null === $collectionUrl) {
            return;
        }

        $this->table->ajax($collectionUrl);
        $this->table->apiPlatform();
    }

    public function getDataTable(): DataTable
    {
        return $this->table;
    }

    /**
     * @return iterable<AbstractColumn>
     */
    public function configureColumns(): iterable
    {
        $columns = $this->getDataTable()->getColumns();
        if ([] !== $columns) {
            return $columns;
        }

        $columns = $this->columnsFromAttributes();
        if ([] !== $columns) {
            return $columns;
        }

        return $this->autoDetectColumns();
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

        $asDataTable = $this->getAsDataTableAttribute();
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

    public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
    {
        return $extensions;
    }

    public function configureButtonsExtension(ButtonsExtension $extension): ButtonsExtension
    {
        return $extension;
    }

    public function configureColumnControlExtension(ColumnControlExtension $extension): ColumnControlExtension
    {
        return $extension;
    }

    public function configureSelectExtension(SelectExtension $extension): SelectExtension
    {
        return $extension;
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        if ($this->table->isServerSide()) {
            return $this->getDataProvider()?->fetchData($request);
        }

        $result = $this->getDataProvider()?->fetchData($request);
        $data   = iterator_to_array($result->data);
        $this->table->data($data);

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
    }

    /**
     * Build columns from #[Column] attributes on the entity class.
     *
     * @return AbstractColumn[]
     */
    protected function columnsFromAttributes(): array
    {
        $reader = $this->attributeColumnReader ?? new AttributeColumnReader();

        $asDataTable = $this->getAsDataTableAttribute();
        if (null === $asDataTable) {
            return [];
        }

        return $reader->readColumns($asDataTable->entityClass);
    }

    /**
     * Auto-detect columns from API Platform metadata.
     *
     * Returns an empty array when auto-detection is not available (API Platform not installed,
     * no #[AsDataTable] attribute, or entity is not an ApiResource).
     *
     * @param string[] $groups Serialization groups to filter properties (defaults to AsDataTable::$serializationGroups)
     *
     * @return AbstractColumn[]
     */
    protected function autoDetectColumns(array $groups = []): array
    {
        if (null === $this->columnAutoDetector) {
            return [];
        }

        $asDataTable = $this->getAsDataTableAttribute();
        if (null === $asDataTable) {
            return [];
        }

        $resolvedGroups = $groups ?: $asDataTable->serializationGroups;

        if (!$this->columnAutoDetector->supports($asDataTable->entityClass)) {
            return [];
        }

        return $this->columnAutoDetector->detectColumns($asDataTable->entityClass, $resolvedGroups);
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
            $this->mapRow(...)
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

    private function configureBooleanColumns(): void
    {
        $asDataTable = $this->getAsDataTableAttribute();
        if (null === $asDataTable) {
            return;
        }

        foreach ($this->columns as $column) {
            if (!$column instanceof BooleanColumn) {
                continue;
            }

            if (null !== $column->getToggleEntityClass()) {
                continue;
            }

            $column->setEntityClass($asDataTable->entityClass);
        }
    }

    private function getAsDataTableAttribute(): ?AsDataTable
    {
        if ($this->asDataTableResolved) {
            return $this->cachedAsDataTable;
        }

        $this->asDataTableResolved = true;

        $attributes = (new \ReflectionClass($this))->getAttributes(AsDataTable::class);
        if (empty($attributes)) {
            return null;
        }

        $this->cachedAsDataTable = $attributes[0]->newInstance();

        return $this->cachedAsDataTable;
    }
}
