<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AjaxDataController
{
    public function __construct(
        private readonly AjaxDataTableRegistry $registry,
        private readonly DataTableProfiler $profiler,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->query->get('table');

        if (!\is_string($token) || '' === $token) {
            throw new NotFoundHttpException('DataTable not found.');
        }

        $table = $this->registry->get($token);

        if (null === $table) {
            throw new NotFoundHttpException('DataTable not found.');
        }

        $start = hrtime(true);

        $table->handleRequest($request);

        if (!$table->isRequestHandled()) {
            throw new BadRequestHttpException('Invalid DataTables request.');
        }

        $response = $table->getResponse();

        $this->collect($table, $token, $response, $start);

        return $response;
    }

    private function collect(AbstractDataTable $table, string $token, JsonResponse $response, float $start): void
    {
        $durationMs = (hrtime(true) - $start) / 1_000_000;

        $payload = json_decode((string) $response->getContent(), true);
        $payload = \is_array($payload) ? $payload : [];

        $this->profiler->collectAjaxQuery(
            class: $table::class,
            token: $token,
            request: $table->getRequest(),
            recordsTotal: (int) ($payload['recordsTotal'] ?? 0),
            recordsFiltered: (int) ($payload['recordsFiltered'] ?? 0),
            durationMs: $durationMs,
        );
    }
}
