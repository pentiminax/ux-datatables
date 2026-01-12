<?php

namespace Pentiminax\UX\DataTables\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Builder\DataTableResponseBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
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
use Pentiminax\UX\DataTables\Query\Strategy\ContainsSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\EmptySearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\EndsWithSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\EqualSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\GreaterOrEqualSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\GreaterThanSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\InListSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\LessOrEqualSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\LessThanSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\NotContainsSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\NotEmptySearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\NotEqualSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\Strategy\StartsWithSearchStrategy;
use Pentiminax\UX\DataTables\RowMapper\ClosureRowMapper;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractDataTable implements DataTableInterface
{
    protected DataTable $table;

    protected ?DataTableRequest $request = null;

    protected EntityManagerInterface $em;

    /**
     * @var AbstractColumn[]
     */
    private array $columns;

    private ?DataProviderInterface $autoConfiguredProvider = null;

    private bool $providerAutoConfigured = false;

    private ?RowMapperInterface $rowMapper = null;

    public function __construct()
    {
        $this->table = $this->configureDataTable(
            new DataTable($this->getClassName())
        );

        $this->columns = iterator_to_array($this->configureColumns());

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

    public function getDataTable(): DataTable
    {
        return $this->table;
    }

    /**
     * @return iterable<AbstractColumn>
     */
    public function configureColumns(): iterable
    {
        return $this->getDataTable()->getColumns();
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

        $classReflection = new \ReflectionClass($this);
        $attributes      = $classReflection->getAttributes(AsDataTable::class);

        if (empty($attributes)) {
            return null;
        }

        /** @var AsDataTable $asDataTable */
        $asDataTable = $attributes[0]->newInstance();

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
        return new SearchStrategyRegistry([
            new EqualSearchStrategy(),
            new NotEqualSearchStrategy(),
            new ContainsSearchStrategy(),
            new NotContainsSearchStrategy(),
            new StartsWithSearchStrategy(),
            new EndsWithSearchStrategy(),
            new GreaterThanSearchStrategy(),
            new GreaterOrEqualSearchStrategy(),
            new LessThanSearchStrategy(),
            new LessOrEqualSearchStrategy(),
            new EmptySearchStrategy(),
            new NotEmptySearchStrategy(),
            new InListSearchStrategy(),
        ]);
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
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
}
