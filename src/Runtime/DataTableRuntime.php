<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class DataTableRuntime
{
    private ?Request $httpRequest = null;

    private ?DataTableRequest $request = null;

    private ?DataProviderInterface $dataProvider = null;

    private bool $dataProviderResolved = false;

    public function __construct(
        private readonly DataTable $table,
        private readonly \Closure $dataProviderFactory,
        private readonly int $maxPageLength = 1000,
    ) {
    }

    public function getRequest(): ?DataTableRequest
    {
        return $this->request;
    }

    public function getHttpRequest(): ?Request
    {
        return $this->httpRequest;
    }

    public function handleRequest(Request $request): void
    {
        $this->httpRequest = $request;
        $this->request     = DataTableRequest::fromRequest($request)
            ->withBoundedLength($this->maxPageLength, $this->allowsShowAll());
    }

    /**
     * Whether the table offers DataTables' "show all" entry, the only case where an
     * unbounded page size is what the developer asked for.
     *
     * lengthMenu() accepts both `[10, 25, -1]` and the two-dimensional
     * `[[10, 25, -1], ['10', '25', 'All']]` form, whose first entry holds the values.
     */
    private function allowsShowAll(): bool
    {
        $menu = $this->table->getOption('lengthMenu');

        if (!\is_array($menu)) {
            return false;
        }

        $values = isset($menu[0]) && \is_array($menu[0]) ? $menu[0] : $menu;

        return \in_array(-1, $values, true);
    }

    public function isRequestHandled(): bool
    {
        return null !== $this->request && $this->request->draw > 0;
    }

    public function getDataProvider(): ?DataProviderInterface
    {
        if ($this->dataProviderResolved) {
            return $this->dataProvider;
        }

        $dataProvider = ($this->dataProviderFactory)();

        $this->dataProviderResolved = true;
        $this->dataProvider         = $dataProvider;

        return $this->dataProvider;
    }

    public function getResponse(): JsonResponse
    {
        if (!$this->request) {
            return $this->createEmptyResponse(
                draw: 1
            );
        }

        $provider = $this->getDataProvider();
        if (null === $provider) {
            return $this->createEmptyResponse($this->request->draw);
        }

        $data = $provider->fetchData($this->request);

        return new JsonResponse([
            'draw'            => $this->request->draw,
            'recordsTotal'    => $data->recordsTotal,
            'recordsFiltered' => $data->recordsFiltered,
            'data'            => iterator_to_array($data->data),
        ]);
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        $provider = $this->getDataProvider();
        if (null === $provider) {
            return $this->createEmptyResult();
        }

        $result = $provider->fetchData($request);
        if ($this->table->isServerSide()) {
            return $result;
        }

        $data = iterator_to_array($result->data);

        $this->table->data($data);
        $this->table->markTemplateColumnsRendered();

        return new DataTableResult(
            recordsTotal: $result->recordsTotal,
            recordsFiltered: $result->recordsFiltered,
            data: $data,
        );
    }

    private function createEmptyResponse(int $draw): JsonResponse
    {
        return new JsonResponse([
            'draw'            => $draw,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
        ]);
    }

    private function createEmptyResult(): DataTableResult
    {
        return new DataTableResult(
            recordsTotal: 0,
            recordsFiltered: 0,
            data: [],
        );
    }
}
