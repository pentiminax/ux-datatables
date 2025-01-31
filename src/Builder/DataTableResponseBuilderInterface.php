<?php



namespace Pentiminax\UX\DataTables\Builder;

use Symfony\Component\HttpFoundation\JsonResponse;

interface DataTableResponseBuilderInterface
{
    public function buildResponse(int $draw = 1, array $data = [], int $recordsTotal = 0, int $recordsFiltered = 0): JsonResponse;
}
