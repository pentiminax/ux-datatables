<?php

namespace Pentiminax\UX\DataTables\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Builder\DataTableResponseBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\RowMapper\ClosureRowMapper;
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
        return null;
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

    public function queryBuilderConfigurator(QueryBuilder $qb, DataTableRequest $request): ?QueryBuilder
    {
        if (1 === count($request->order)) {
            $order  = $request->order[0];
            $column = $request->columns->getColumnByIndex($order->column);
            $qb->addOrderBy(sprintf('e.%s', $column?->name), $order->dir);
        }

        $searchableColumns = \array_filter($this->columns, static fn (AbstractColumn $column) => $column->isSearchable());

        if ([] !== $searchableColumns) {
            $searchValue = $request->search->value;
            $conditions  = [];

            foreach ($searchableColumns as $index => $column) {
                if ($column instanceof TextColumn && $searchValue) {
                    $paramName    = sprintf('search_param_%d', $index);
                    $conditions[] = sprintf('e.%s LIKE :%s', $column->getName(), $paramName);
                    $qb->setParameter($paramName, "%$searchValue%");
                    continue;
                }

                if ($column->isNumber()) {
                    if (!is_numeric($searchValue)) {
                        continue;
                    }

                    $paramName    = sprintf('search_param_%d', $index);
                    $conditions[] = sprintf('e.%s = :%s', $column->getName(), $paramName);
                    $qb->setParameter($paramName, $searchValue);
                }
            }

            if ([] !== $conditions) {
                $qb->andWhere(
                    $qb->expr()->orX(...$conditions)
                );
            }
        }

        foreach ($searchableColumns as $index => $column) {
            $columnControlSearch = $request->columns->getColumnByIndex($index)?->columnControl?->search;

            if ($columnControlSearch) {
                $this->applyColumnControlSearch($qb, $column, $columnControlSearch, $index);
            }
        }

        return $qb;
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

    protected function mapRow(mixed $item): array
    {
        return is_array($item) ? $item : get_object_vars($item);
    }

    protected function rowMapper(): RowMapperInterface
    {
        return new ClosureRowMapper(
            $this->mapRow(...)
        );
    }

    private function getClassName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    private function applyColumnControlSearch(QueryBuilder $qb, AbstractColumn $column, ?ColumnControlSearch $search, int $index): void
    {
        if (null === $search) {
            return;
        }

        $logic = $search->logic;
        $type  = strtolower($search->type);
        $value = $search->value;
        $field = sprintf('e.%s', $column->getName());
        $expr  = $qb->expr();
        $isNumeric = $column->isNumber() || \in_array($type, ['number', 'numeric', 'num'], true);

        if ('' === trim($value) && !\in_array($logic, ['empty', 'notEmpty'], true)) {
            return;
        }

        if ('empty' === $logic) {
            $qb->andWhere($isNumeric
                ? $expr->isNull($field)
                : $expr->orX(
                    $expr->isNull($field),
                    $expr->eq($field, $expr->literal(''))
                ));

            return;
        }

        if ('notEmpty' === $logic) {
            $qb->andWhere($isNumeric
                ? $expr->isNotNull($field)
                : $expr->andX(
                    $expr->isNotNull($field),
                    $expr->neq($field, $expr->literal(''))
                ));

            return;
        }

        $paramName  = sprintf('column_control_param_%d', $index);
        $paramValue = $value;

        switch ($logic) {
            case 'equal':
                $qb->andWhere(sprintf('%s = :%s', $field, $paramName));
                break;
            case 'notEqual':
                $qb->andWhere(sprintf('%s != :%s', $field, $paramName));
                break;
            case 'starts':
                $qb->andWhere(sprintf('%s LIKE :%s', $field, $paramName));
                $paramValue = sprintf('%s%%', $value);
                break;
            case 'ends':
                $qb->andWhere(sprintf('%s LIKE :%s', $field, $paramName));
                $paramValue = sprintf('%%%s', $value);
                break;
            case 'notContains':
                $qb->andWhere(sprintf('%s NOT LIKE :%s', $field, $paramName));
                $paramValue = sprintf('%%%s%%', $value);
                break;
            case 'greater':
                $qb->andWhere(sprintf('%s > :%s', $field, $paramName));
                break;
            case 'greaterOrEqual':
                $qb->andWhere(sprintf('%s >= :%s', $field, $paramName));
                break;
            case 'less':
                $qb->andWhere(sprintf('%s < :%s', $field, $paramName));
                break;
            case 'lessOrEqual':
                $qb->andWhere(sprintf('%s <= :%s', $field, $paramName));
                break;
            case 'contains':
            default:
                if ($isNumeric) {
                    if (!is_numeric($value)) {
                        return;
                    }
                    $qb->andWhere(sprintf('%s = :%s', $field, $paramName));
                    break;
                }
                $qb->andWhere(sprintf('%s LIKE :%s', $field, $paramName));
                $paramValue = sprintf('%%%s%%', $value);
                break;
        }

        $qb->setParameter($paramName, $paramValue);
    }
}
