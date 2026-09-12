<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\SourceRowResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AjaxTemplateRenderController
{
    public function __construct(
        private readonly AjaxDataTableRegistry $registry,
        private readonly DataTableRuntimeFactory $runtimeFactory,
        private readonly SourceRowResolver $sourceRowResolver,
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

        $rows = $payload->all()['rows'] ?? [];

        if (!\is_array($rows) || [] === $rows) {
            throw new BadRequestHttpException('No rows provided.');
        }

        if (\count($rows) > $this->maxRows) {
            throw new BadRequestHttpException('Too many rows.');
        }

        $sourceRows = $this->sourceRowResolver->resolve($table->getEntityClass(), $rows);

        $data = [];
        foreach ($rows as $key => $row) {
            $data[] = $this->renderRow($row, $table, $sourceRows[$key] ?? null);
        }

        return new JsonResponse(['data' => $data]);
    }

    private function renderRow(mixed $row, AbstractDataTable $table, ?object $sourceRow): mixed
    {
        if (!\is_array($row)) {
            return $row;
        }

        // No source entity means API Platform did not serve this row to the current user.
        // Rendering it would run Twig, actions and URL generation on client-supplied data.
        if (null === $sourceRow) {
            return $row;
        }

        $columns = $table->getConfiguredDataTable()->getColumns();

        return $this->runtimeFactory
            ->createRowMapper(
                baseMapper: static fn (): array => $row,
                columns: $columns,
                dataTableClass: $table::class,
            )
            ->map($sourceRow);
    }
}
