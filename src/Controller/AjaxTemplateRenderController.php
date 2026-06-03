<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
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
        private readonly ?ManagerRegistry $doctrine = null,
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

        return new JsonResponse([
            'data' => array_map(fn (mixed $row): mixed => $this->renderRow($row, $table), $rows),
        ]);
    }

    private function renderRow(mixed $row, AbstractDataTable $table): mixed
    {
        if (!\is_array($row)) {
            return $row;
        }

        $sourceRow = $this->resolveSourceRow($row, $table) ?? $row;
        $columns   = $table->getConfiguredDataTable()->getColumns();

        return $this->runtimeFactory
            ->createRowMapper(
                baseMapper: static fn (): array => $row,
                columns: $columns,
            )
            ->map($sourceRow);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveSourceRow(array $row, AbstractDataTable $table): ?object
    {
        $entityClass = $table->getEntityClass();
        if (null === $entityClass || null === $this->doctrine) {
            return null;
        }

        $id = $this->resolveIdentifier($row);
        if (null === $id) {
            return null;
        }

        return $this->doctrine->getRepository($entityClass)->find($id);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveIdentifier(array $row): string|int|null
    {
        $id = $row['id'] ?? null;
        if (\is_string($id) || \is_int($id)) {
            return $id;
        }

        $iri = $row['@id'] ?? null;
        if (!\is_string($iri) || '' === trim($iri)) {
            return null;
        }

        $lastSegment = basename(parse_url($iri, \PHP_URL_PATH) ?: '');

        return '' === $lastSegment ? null : $lastSegment;
    }
}
