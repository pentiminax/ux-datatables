<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Rehydration\SourceRowMap;
use Pentiminax\UX\DataTables\Rehydration\SourceRowResolver;
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

        if ([] === $rows) {
            throw new BadRequestHttpException('No rows provided.');
        }

        $sourceRows = $this->sourceRowResolver->resolve($table->getEntityClass(), $rows);

        return new JsonResponse([
            'data' => array_map(fn (mixed $row): mixed => $this->renderRow($row, $table, $sourceRows), $rows),
        ]);
    }

    private function renderRow(mixed $row, AbstractDataTable $table, SourceRowMap $sourceRows): mixed
    {
        if (!\is_array($row)) {
            return $row;
        }

        $sourceRow = $sourceRows->sourceFor($row) ?? $row;
        $columns   = $table->getConfiguredDataTable()->getColumns();

        return $this->runtimeFactory
            ->createRowMapper(
                baseMapper: static fn (): array => $row,
                columns: $columns,
            )
            ->map($sourceRow);
    }
}
