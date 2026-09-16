<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformQueryParameterFactory;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ResolvedCollectionOperation;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\StreamingDataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads rows through the API Platform collection operation the table is bound to.
 *
 * One call to the state provider serves a whole page. The provider chain is invoked in process --
 * this is a service call, not an HTTP sub-request, so no kernel event is dispatched and nothing in
 * the Symfony firewall is skipped that a Doctrine-backed table would have run. The operation's
 * `security` expression, its query parameters' `security`, its state provider and its query
 * extensions all apply, because the chain reached through `api_platform.state_provider.main`
 * carries AccessCheckerProvider, SecurityParameterProvider, ParameterProvider and ReadProvider.
 *
 * Authorization therefore lives on the operation, not on the collection's URL path: `access_control`
 * rules matching that path are not evaluated here, exactly as they are not for a Doctrine table.
 */
class ApiPlatformCollectionProvider implements DataProviderInterface, StreamingDataProviderInterface
{
    /**
     * @param list<ColumnInterface> $columns configured columns in display order, before permission filtering
     */
    public function __construct(
        private readonly ProviderInterface $stateProvider,
        private readonly ApiResourceCollectionUrlResolver $collectionResolver,
        private readonly ApiPlatformQueryParameterFactory $queryParameterFactory,
        private readonly DefaultDataTableQueryIntentFactory $intentFactory,
        private readonly ColumnResolver $columnResolver,
        private readonly RequestStack $requestStack,
        private readonly string $entityClass,
        private readonly array $columns,
        private readonly RowMapperInterface $rowMapper,
        private readonly ?RowMapperInterface $exportRowMapper = null,
        private readonly ?string $dataTableClass = null,
        private readonly int $exportChunkSize = 250,
    ) {
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        $parameters = $this->queryParameters($request);
        $data       = $this->provide($parameters);

        if ($data instanceof PaginatorInterface) {
            $total = max(0, (int) $data->getTotalItems());

            return new DataTableResult($total, $total, $this->mapRows($data, $this->rowMapper));
        }

        $items = $this->toList($data);
        $total = \count($items);

        // Hydra exposes a single count, the filtered one, so recordsTotal cannot be narrower than
        // recordsFiltered here. This matches what the browser-side adapter reports today.
        return new DataTableResult($total, $total, $this->mapRows($items, $this->rowMapper));
    }

    /**
     * An export arrives without pagination, so it walks the collection one page at a time rather
     * than asking the operation for everything at once. Requesting `pagination=false` would only
     * work on operations that opted into client-controlled pagination.
     *
     * The requested chunk size says nothing about where the collection ends: an operation capping
     * itemsPerPage returns short pages forever, and one with pagination disabled returns everything
     * on the first call. Only the paginator the operation hands back knows, so termination is read
     * from it rather than from the number of rows that came back.
     */
    public function iterateRows(DataTableRequest $request): iterable
    {
        $parameters = $this->queryParameters($request);
        $mapper     = $this->exportRowMapper ?? $this->rowMapper;
        $pageSize   = max(1, $this->exportChunkSize);

        for ($page = 1;; ++$page) {
            $data = $this->provide([
                'page'         => (string) $page,
                'itemsPerPage' => (string) $pageSize,
            ] + $parameters);
            $items = $this->toList($data);

            foreach ($items as $item) {
                yield $mapper->map($item);
            }

            if ([] === $items || !$this->hasPageAfter($data, $page, \count($items))) {
                return;
            }
        }
    }

    /**
     * A full paginator knows its last page. A partial one only knows the page size it actually
     * applied, so a full page is the signal to ask for the next one. Anything else -- a plain array
     * or an operation with pagination disabled -- returned the whole collection at once.
     */
    private function hasPageAfter(mixed $data, int $page, int $returned): bool
    {
        if ($data instanceof PaginatorInterface) {
            return $page < $data->getLastPage();
        }

        if ($data instanceof PartialPaginatorInterface) {
            return $returned >= max(1, (int) $data->getItemsPerPage());
        }

        return false;
    }

    /**
     * @return array<string, string|array<int|string, string>>
     */
    private function queryParameters(DataTableRequest $request): array
    {
        $intent = $this->intentFactory->create(
            $request,
            array_values($this->columnResolver->filterStaticPermissions($this->columns, $this->dataTableClass)),
        );

        return $this->queryParameterFactory->create($intent, $request);
    }

    /**
     * @param array<string, string|array<int|string, string>> $parameters
     */
    private function provide(array $parameters): mixed
    {
        $resolved = $this->collectionResolver->resolveCollection($this->entityClass);

        if (null === $resolved) {
            throw new \LogicException(\sprintf('No API Platform collection operation was found for "%s".', $this->entityClass));
        }

        $request = $this->createOperationRequest($resolved, $parameters);

        return $this->stateProvider->provide($resolved->operation, [], [
            'request'        => $request,
            'resource_class' => $this->entityClass,
            'operation'      => $resolved->operation,
            'uri_variables'  => [],
        ]);
    }

    /**
     * ParameterProvider reads query parameter values off a Request, so the chain needs one even
     * though nothing here is served over HTTP. Only the locale and the session are carried over
     * from the live request: copying its server bag would drag a POST's Content-Type onto a GET.
     *
     * @param array<string, string|array<int|string, string>> $parameters
     */
    private function createOperationRequest(ResolvedCollectionOperation $resolved, array $parameters): Request
    {
        $query = http_build_query($parameters);

        $request = Request::create('' === $query ? $resolved->url : $resolved->url.'?'.$query);

        $request->attributes->set('_api_resource_class', $this->entityClass);
        $request->attributes->set('_api_operation_name', $resolved->operation->getName());
        $request->attributes->set('_api_operation', $resolved->operation);

        // Matches the Accept the browser sends today, so content negotiation picks the same format.
        $request->headers->set('Accept', '*/*');

        $current = $this->requestStack->getCurrentRequest();
        if (null === $current) {
            return $request;
        }

        $request->setLocale($current->getLocale());
        $request->setDefaultLocale($current->getDefaultLocale());

        if ($current->hasSession()) {
            $request->setSession($current->getSession());
        }

        return $request;
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function mapRows(iterable $items, RowMapperInterface $mapper): \Generator
    {
        foreach ($items as $item) {
            yield $mapper->map($item);
        }
    }

    /**
     * @return list<mixed>
     */
    private function toList(mixed $data): array
    {
        if (null === $data) {
            return [];
        }

        if (\is_array($data)) {
            return array_values($data);
        }

        if ($data instanceof \Traversable) {
            return iterator_to_array($data, false);
        }

        return [$data];
    }
}
