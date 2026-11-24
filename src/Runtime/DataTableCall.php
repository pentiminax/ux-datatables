<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Column\VisibleColumns;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\BoundTableHooks;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * One table serving one request: the per-request state that used to live on the shared table.
 *
 * It owns the request, the permission-filtered columns, the row mappers, and the resolved data
 * provider, and is dropped with the request. {@see DataTableRuntime} is its only factory.
 *
 * @internal
 */
final class DataTableCall
{
    /**
     * The table bound to this call, so the hooks it overrides read this request and nothing shared.
     */
    public readonly BoundTableHooks $hooks;

    private ?VisibleColumns $visibleColumns = null;

    private ?RowMapperInterface $rowMapper = null;

    private ?RowMapperInterface $exportRowMapper = null;

    private ?DefaultRowMapper $defaultRowMapper = null;

    private ?DataProviderInterface $dataProvider = null;

    private bool $dataProviderResolved = false;

    public function __construct(
        private readonly DataTableRuntime $runtime,
        public readonly TableDefinition $definition,
        AbstractDataTable $table,
        public readonly ?Request $httpRequest = null,
        public readonly ?DataTableRequest $request = null,
    ) {
        $this->hooks = $table->bindContext(new TableContext(
            request: $request,
            httpRequest: $httpRequest,
            columns: fn (): array => $this->visibleColumns()->columns,
            rowMapper: fn (): RowMapperInterface => $this->runtime->createRowMapper($this),
            defaultRowMapper: fn (mixed $row): array => $this->defaultRowMapper()->map($row),
        ));
    }

    public function isHandled(): bool
    {
        return null !== $this->request && $this->request->draw > 0;
    }

    /**
     * Resolved on the first row, once permissions can be checked.
     */
    public function visibleColumns(): VisibleColumns
    {
        return $this->visibleColumns ??= $this->runtime->visibleColumns($this->definition);
    }

    /**
     * The row pipeline of the displayed table.
     */
    public function rowMapper(): RowMapperInterface
    {
        return $this->rowMapper ??= $this->runtime->createRowMapper($this, visibleColumns: true);
    }

    /**
     * The row pipeline of an export, built from the exportable columns alone.
     */
    public function exportRowMapper(): RowMapperInterface
    {
        return $this->exportRowMapper ??= $this->runtime->createExportRowMapper($this);
    }

    /**
     * Data, export and bulk requests all reach the provider through here, so once a DataTables
     * request is handled the provider is never handed out with criteria it cannot apply. A page load
     * (draw 0) is not checked: its query string is the page's, not a DataTables request.
     */
    public function dataProvider(): ?DataProviderInterface
    {
        if ($this->dataProviderResolved) {
            return $this->dataProvider;
        }

        $dataProvider = $this->runtime->createDataProvider($this);

        if (null !== $dataProvider && $this->isHandled()) {
            $this->runtime->guard()->assertSatisfied($this->definition, $dataProvider);
            $this->runtime->guard()->assertRequestSupported($this->request, $dataProvider, $this->definition->table, $this->definition->columns);
        }

        $this->dataProviderResolved = true;

        return $this->dataProvider = $dataProvider;
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        $provider = $this->dataProvider();
        if (null === $provider) {
            return self::emptyResult();
        }

        if ($request !== $this->request) {
            $this->runtime->guard()->assertRequestSupported($request, $provider, $this->definition->table, $this->definition->columns);
        }

        return $provider->fetchData($request);
    }

    public function response(): JsonResponse
    {
        if (null === $this->request) {
            return new JsonResponse(['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }

        $provider = $this->dataProvider();
        $data     = null === $provider ? self::emptyResult() : $provider->fetchData($this->request);
        $payload  = [
            'draw'            => $this->request->draw,
            'recordsTotal'    => $data->recordsTotal,
            'recordsFiltered' => $data->recordsFiltered,
            'data'            => iterator_to_array($data->data),
        ];

        if ([] !== ($data->summaries ?? [])) {
            $payload['summaries'] = $data->summaries;
        }

        $searchListOptions = $this->runtime->searchListOptions($this);
        if (null !== $searchListOptions) {
            $payload['columnControl'] = $searchListOptions;
        }

        return new JsonResponse($payload);
    }

    private function defaultRowMapper(): DefaultRowMapper
    {
        return $this->defaultRowMapper ??= new DefaultRowMapper($this->definition->columns);
    }

    private static function emptyResult(): DataTableResult
    {
        return new DataTableResult(recordsTotal: 0, recordsFiltered: 0, data: []);
    }
}
