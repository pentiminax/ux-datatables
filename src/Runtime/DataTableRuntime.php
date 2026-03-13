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
    private ?DataTableRequest $request = null;

    private ?DataProviderInterface $dataProvider = null;

    private bool $dataProviderResolved = false;

    public function __construct(
        private readonly DataTable $table,
        private readonly \Closure $dataProviderFactory,
    ) {
    }

    public function getRequest(): ?DataTableRequest
    {
        return $this->request;
    }

    public function handleRequest(Request $request): void
    {
        $this->request = DataTableRequest::fromRequest($request);
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

        $this->dataProviderResolved = true;
        $this->dataProvider         = ($this->dataProviderFactory)();

        return $this->dataProvider;
    }

    public function getResponse(): JsonResponse
    {
        if (!$this->request) {
            return $this->createEmptyResponse(1);
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
