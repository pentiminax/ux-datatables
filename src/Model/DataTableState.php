<?php

namespace Pentiminax\UX\DataTables\Model;

use Symfony\Component\HttpFoundation\ParameterBag;

final class DataTableState
{
    private int $draw = 0;
    private int $start = 0;
    private ?int $length = null;
    private string $globalSearch = '';

    private bool $isCallback = false;

    private array $orderBy = [];

    private array $columnControlSearch = [];

    public function __construct(
        private DataTable $dataTable
    )
    {
    }

    public static function fromDefaults(DataTable $dataTable): static
    {
        $state = new static($dataTable);
        $state->start = (int)$dataTable->getOption('start');
        $state->length = (int)$dataTable->getOption('pageLength');


        foreach ($dataTable->getOption('order') ?? [] as $order) {
            $state->addOrderBy($dataTable->getColumnByIndex($order[0]), $order[1]);
        }

        return $state;
    }

    public function applyParameters(ParameterBag $parameters): void
    {
        $this->draw = $parameters->getInt('draw');
        $this->isCallback = true;
        $this->start = (int)$parameters->get('start', $this->start);
        $this->length = (int)$parameters->get('length', $this->length);

        if ($this->length < 1) {
            $this->length = null;
        }

        $search = $parameters->all()['search'] ?? [];

        $this->setGlobalSearch($search['value'] ?? $this->globalSearch);

        $this->handleOrderBy($parameters);
        $this->handleColumnControl($parameters);
    }

    private function handleOrderBy(ParameterBag $parameters): void
    {
        if ($parameters->has('order')) {
            $this->orderBy = [];
            foreach ($parameters->all()['order'] ?? [] as $order) {
                try {
                    $column = $this->dataTable->getColumnByIndex((int)$order['column']);
                    $this->addOrderBy($column, $order['dir'] ?? 'asc');
                } catch (\Throwable) {
                    // Column index and direction can be corrupted by malicious clients, ignore any exceptions thus caused
                }
            }
        }
    }

    private function handleColumnControl(ParameterBag $parameterBag): void
    {
        foreach ($parameterBag->all()['columns'] as $column) {
            if (isset($column['columnControl'])) {
                $this->columnControlSearch[] = [
                    $this->dataTable->getColumnByName($column['name']),
                    $column['columnControl']
                ];
            }
        }
    }

    public function addOrderBy(ColumnInterface $column, string $direction = 'asc'): static
    {
        $direction = mb_strtolower($direction);

        $this->orderBy[] = [$column, $direction];

        return $this;
    }

    public function getDraw(): int
    {
        return $this->draw;
    }

    public function getGlobalSearch(): string
    {
        return $this->globalSearch;
    }

    public function setGlobalSearch(string $globalSearch): static
    {
        $this->globalSearch = $globalSearch;

        return $this;
    }

    public function isCallback(): bool
    {
        return $this->isCallback;
    }

    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getColumnControlSearch(): array
    {
        return $this->columnControlSearch;
    }
}