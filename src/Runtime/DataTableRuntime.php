<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
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
        /** @var list<ColumnInterface> */
        private readonly array $columns = [],
        private readonly SearchListOptionsResolver $searchListOptionsResolver = new SearchListOptionsResolver(),
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
        $dataTableRequest  = DataTableRequest::fromRequest($request);

        // A refused "show all" falls back to the configured bound: DataTables renders that one
        // page and offers no other, so a smaller page truncates rather than strands rows.
        $maxLength = $dataTableRequest->length > 0 ? $this->maxRequestedLength() : $this->maxPageLength;

        $this->request = $dataTableRequest->withBoundedLength($maxLength, $this->allowsShowAll());
    }

    /**
     * The cap a requested page size is narrowed to.
     *
     * A page size the table itself declares is a developer decision, not crafted input, so it
     * raises the bound instead of being silently truncated: capping it below the length the
     * client paginates with would leave the rows between the two sizes unreachable.
     */
    private function maxRequestedLength(): int
    {
        $declared = [$this->table->getOption('pageLength'), ...$this->lengthMenuValues()];

        return max($this->maxPageLength, ...array_map(
            static fn (mixed $value): int => \is_int($value) && $value > 0 ? $value : 0,
            $declared,
        ));
    }

    /**
     * Whether the table offers DataTables' "show all" entry, the only case where an
     * unbounded page size is what the developer asked for.
     */
    private function allowsShowAll(): bool
    {
        foreach ($this->lengthMenuValues() as $value) {
            if (-1 === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * The page sizes declared through lengthMenu(), which accepts `[10, 25, -1]`, the
     * two-dimensional `[[10, 25, -1], ['10', '25', 'All']]` form, and associative entries such
     * as `['label' => 'All', 'value' => -1]`.
     *
     * @return list<mixed>
     */
    private function lengthMenuValues(): array
    {
        $menu = $this->table->getOption('lengthMenu');

        if (!\is_array($menu)) {
            return [];
        }

        $entries = isset($menu[0]) && \is_array($menu[0]) && array_is_list($menu[0])
            ? $menu[0]
            : $menu;

        $values = [];
        foreach ($entries as $entry) {
            $values[] = \is_array($entry) ? ($entry['value'] ?? null) : $entry;
        }

        return $values;
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
        $data     = null === $provider ? $this->createEmptyResult() : $provider->fetchData($this->request);
        $payload  = [
            'draw'            => $this->request->draw,
            'recordsTotal'    => $data->recordsTotal,
            'recordsFiltered' => $data->recordsFiltered,
            'data'            => iterator_to_array($data->data),
        ];

        $searchListOptions = $this->searchListOptionsResolver->resolve($this->table, $this->columns, $this->request);
        if (null !== $searchListOptions) {
            $payload['columnControl'] = $searchListOptions;
        }

        return new JsonResponse($payload);
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
