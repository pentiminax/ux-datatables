<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Test;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds and sends a DataTables Ajax request for a server-side table.
 *
 * Every configuration method returns a new instance, so a builder prepared once can be branched
 * per test without leaking state between them.
 */
final class DataTableRequestBuilder
{
    private const string ROUTE = 'ux_datatables_ajax_data';

    private int $draw = 1;

    private int $start = 0;

    private int $length = 10;

    private string $search = '';

    private bool $searchRegex = false;

    /** @var list<array{string, string}> column name and direction */
    private array $orders = [];

    /** @var array<string, mixed> */
    private array $filters = [];

    /** @var list<array<string, mixed>>|null */
    private ?array $columnsOverride = null;

    /** @var array<string, mixed> */
    private array $extraQuery = [];

    /**
     * @param class-string<AbstractDataTable> $dataTableClass
     */
    public function __construct(
        private readonly KernelBrowser $client,
        private readonly AjaxDataTableRegistry $registry,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $dataTableClass,
    ) {
    }

    public function draw(int $draw): self
    {
        $clone       = clone $this;
        $clone->draw = $draw;

        return $clone;
    }

    public function start(int $start): self
    {
        $clone        = clone $this;
        $clone->start = $start;

        return $clone;
    }

    public function length(int $length): self
    {
        $clone         = clone $this;
        $clone->length = $length;

        return $clone;
    }

    /**
     * Page numbers are 1-indexed and resolved against the current length.
     */
    public function page(int $page): self
    {
        if ($page < 1) {
            throw new \LogicException(\sprintf('Page numbers are 1-indexed, got %d.', $page));
        }

        return $this->start(($page - 1) * $this->length);
    }

    public function search(string $value, bool $regex = false): self
    {
        $clone              = clone $this;
        $clone->search      = $value;
        $clone->searchRegex = $regex;

        return $clone;
    }

    public function orderBy(string $columnName, string $direction = 'asc'): self
    {
        $clone           = clone $this;
        $clone->orders[] = [$columnName, $direction];

        return $clone;
    }

    public function filter(string $name, mixed $value): self
    {
        $clone                 = clone $this;
        $clone->filters[$name] = $value;

        return $clone;
    }

    /**
     * @param list<array<string, mixed>> $columns replaces the payload derived from the table
     */
    public function columns(array $columns): self
    {
        $clone                  = clone $this;
        $clone->columnsOverride = $columns;

        return $clone;
    }

    /**
     * @param array<string, mixed> $parameters merged last, so they override everything derived
     */
    public function query(array $parameters): self
    {
        $clone             = clone $this;
        $clone->extraQuery = [...$clone->extraQuery, ...$parameters];

        return $clone;
    }

    public function fetch(): DataTableResponse
    {
        return DataTableResponse::fromResponse($this->fetchRaw(), $this->dataTableClass);
    }

    /**
     * The untouched response, for the cases a successful payload cannot describe: an access
     * denial, a redirect to the login form, a missing table.
     */
    public function fetchRaw(): Response
    {
        $this->client->request('GET', $this->route(), $this->toQueryParameters());

        return $this->client->getResponse();
    }

    /**
     * The path the application actually serves the Ajax endpoint on, which is not necessarily
     * /datatables/ajax/data: an application is free to import the bundle's routes under a prefix.
     */
    private function route(): string
    {
        try {
            return $this->urlGenerator->generate(self::ROUTE);
        } catch (RouteNotFoundException $exception) {
            throw new \LogicException(\sprintf('Route "%s" is not registered. Import the bundle routes in the kernel under test.', self::ROUTE), previous: $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toQueryParameters(): array
    {
        $columns = $this->columnsOverride ?? $this->derivedColumns();

        $parameters = [
            'table'  => $this->token(),
            'draw'   => $this->draw,
            'start'  => $this->start,
            'length' => $this->length,
            'search' => [
                'value' => $this->search,
                'regex' => $this->searchRegex ? 'true' : 'false',
            ],
            'columns' => $columns,
        ];

        foreach ($this->orders as [$columnName, $direction]) {
            $parameters['order'][] = [
                'column' => $this->columnIndex($columnName, $columns),
                'dir'    => $direction,
            ];
        }

        if ([] !== $this->filters) {
            $parameters['filters'] = $this->filters;
        }

        return [...$parameters, ...$this->extraQuery];
    }

    private function token(): string
    {
        $token = $this->registry->getToken($this->dataTableClass);

        if (null === $token) {
            throw $this->unregisteredTable();
        }

        return $token;
    }

    /**
     * The column payload DataTables would send, derived from the table's own configuration so
     * that a test never spells out `columns[0][data]` by hand.
     *
     * @return list<array<string, mixed>>
     */
    private function derivedColumns(): array
    {
        $table = $this->registry->get($this->token());

        if (null === $table) {
            throw $this->unregisteredTable();
        }

        return array_values(array_map(
            static fn (ColumnInterface $column): array => [
                'data'       => $column->getData() ?? $column->getName(),
                'name'       => $column->getName(),
                'searchable' => $column->isSearchable() ? 'true' : 'false',
                'orderable'  => $column->isOrderable() ? 'true' : 'false',
            ],
            array_values($table->getConfiguredDataTable()->getColumns()),
        ));
    }

    /**
     * @param list<array<string, mixed>> $columns
     */
    private function columnIndex(string $columnName, array $columns): int
    {
        foreach (array_values($columns) as $index => $column) {
            if (($column['name'] ?? null) === $columnName) {
                return $index;
            }
        }

        $names = array_filter(array_column($columns, 'name'));

        throw new \LogicException(\sprintf('Unknown column "%s". Available columns: %s.', $columnName, implode(', ', $names)));
    }

    private function unregisteredTable(): \LogicException
    {
        return new \LogicException(\sprintf('DataTable "%s" is not registered. Add the #[AsDataTable] attribute or register it as a service in the kernel under test.', $this->dataTableClass));
    }
}
