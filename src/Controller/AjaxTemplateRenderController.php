<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformItemResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadGatewayHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AjaxTemplateRenderController
{
    public function __construct(
        private readonly AjaxDataTableRegistry $registry,
        private readonly DataTableRuntimeFactory $runtimeFactory,
        private readonly HttpKernelInterface $httpKernel,
        private readonly RequestStack $requestStack,
        private readonly ?ApiResourceCollectionUrlResolver $collectionUrlResolver,
        private readonly ?ApiPlatformItemResolver $itemResolver,
        private readonly int $maxRows,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getPayload();
        $token   = $payload->getString('table');

        if ('' === $token) {
            throw new NotFoundHttpException('DataTable not found.');
        }

        $table = $this->registry->get($token);

        if (null === $table) {
            throw new NotFoundHttpException('DataTable not found.');
        }

        $entityClass = $table->getEntityClass();
        if (null === $entityClass || null === $this->collectionUrlResolver) {
            throw new BadRequestHttpException('API Platform collection is unavailable.');
        }

        $collectionUrl = $this->collectionUrlResolver->resolveCollectionUrl($entityClass);
        if (null === $collectionUrl) {
            throw new BadRequestHttpException('API Platform collection is unavailable.');
        }

        $query = $payload->getString('query');
        if ('' === $query) {
            throw new BadRequestHttpException('No collection query provided.');
        }

        $subRequest = $this->createCollectionRequest($request, $collectionUrl, $query);
        $response   = $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

        if (!$response->isSuccessful()) {
            throw new HttpException($response->getStatusCode(), 'API Platform collection request failed.');
        }

        $collection     = $this->decodeCollection($response->getContent());
        $serializedRows = $this->serializedRows($collection);
        $sourceData     = $subRequest->attributes->get('data');

        if (!\is_array($sourceData) && !$sourceData instanceof \Traversable) {
            throw new BadGatewayHttpException('API Platform collection returned no readable data.');
        }

        $sourceRows = \is_array($sourceData) ? array_values($sourceData) : iterator_to_array($sourceData, false);

        if (\count($serializedRows) > $this->maxRows || \count($sourceRows) > $this->maxRows) {
            throw new BadRequestHttpException('Too many rows.');
        }

        $data  = $this->renderRows($table, $entityClass, $serializedRows, $sourceRows);
        $total = $this->totalItems($collection, \count($serializedRows));

        return new JsonResponse([
            'draw'            => max(0, $payload->getInt('draw')),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data,
        ]);
    }

    private function createCollectionRequest(Request $request, string $collectionUrl, string $query): Request
    {
        $parentRequest = $this->requestStack->getCurrentRequest() ?? $request;
        $query         = $this->boundPageLength(ltrim($query, '?'));
        $separator     = str_contains($collectionUrl, '?') ? '&' : '?';
        $subRequest    = Request::create(
            $collectionUrl.$separator.$query,
            Request::METHOD_GET,
            cookies: $parentRequest->cookies->all(),
            server: $parentRequest->server->all(),
        );

        if ($parentRequest->hasSession()) {
            $subRequest->setSession($parentRequest->getSession());
        }

        $subRequest->setLocale($parentRequest->getLocale());
        $subRequest->setDefaultLocale($parentRequest->getDefaultLocale());

        return $subRequest;
    }

    private function boundPageLength(string $query): string
    {
        $parameters = [];
        $bounded    = false;

        foreach (explode('&', $query) as $parameter) {
            if ('' === $parameter) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $parameter, 2), 2, '');
            if ('itemsPerPage' !== urldecode($name)) {
                $parameters[] = $parameter;

                continue;
            }

            if ($bounded) {
                continue;
            }

            $length       = max(1, min($this->maxRows, (int) urldecode($value)));
            $parameters[] = $name.'='.$length;
            $bounded      = true;
        }

        if (!$bounded) {
            $parameters[] = 'itemsPerPage='.$this->maxRows;
        }

        return implode('&', $parameters);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<array-key, mixed>>
     */
    private function serializedRows(array $payload): array
    {
        $rows = $payload['hydra:member'] ?? $payload['member'] ?? null;

        if (!\is_array($rows)) {
            throw new BadGatewayHttpException('API Platform collection returned no readable rows.');
        }

        foreach ($rows as $row) {
            if (!\is_array($row)) {
                throw new BadGatewayHttpException('API Platform collection returned an invalid row.');
            }
        }

        return array_values($rows);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function totalItems(array $payload, int $fallback): int
    {
        $total = $payload['hydra:totalItems'] ?? $payload['totalItems'] ?? $fallback;

        return is_numeric($total) ? max(0, (int) $total) : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeCollection(string|false $content): array
    {
        if (false === $content) {
            throw new BadGatewayHttpException('API Platform collection returned an unreadable response.');
        }

        try {
            $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadGatewayHttpException('API Platform collection returned an unreadable response.', $exception);
        }

        if (!\is_array($payload)) {
            throw new BadGatewayHttpException('API Platform collection returned an unreadable response.');
        }

        return $payload;
    }

    /**
     * @param list<array<array-key, mixed>> $serializedRows
     * @param list<mixed>                   $sourceRows
     *
     * @return list<array<array-key, mixed>>
     */
    private function renderRows(AbstractDataTable $table, string $entityClass, array $serializedRows, array $sourceRows): array
    {
        if (\count($serializedRows) !== \count($sourceRows)) {
            return $serializedRows;
        }

        $baseRow   = [];
        $rowMapper = $this->runtimeFactory->createRowMapper(
            baseMapper: static function () use (&$baseRow): array {
                return $baseRow;
            },
            columns: $table->getConfiguredDataTable()->getColumns(),
            dataTableClass: $table::class,
        );

        $data = [];
        foreach ($sourceRows as $index => $sourceRow) {
            $baseRow = $serializedRows[$index];

            if (!\is_object($sourceRow) || null === $this->itemResolver) {
                $data[] = $baseRow;

                continue;
            }

            if (!$this->itemResolver->isGranted($entityClass, $sourceRow, $baseRow)) {
                $data[] = $baseRow;

                continue;
            }

            $data[] = $rowMapper->map($sourceRow);
        }

        return $data;
    }
}
