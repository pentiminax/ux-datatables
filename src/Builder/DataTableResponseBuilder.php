<?php

namespace Pentiminax\UX\DataTables\Builder;

use Symfony\Component\HttpFoundation\JsonResponse;

class DataTableResponseBuilder implements DataTableResponseBuilderInterface
{
    public function buildResponse(int $draw = 1, array $data = [], int $recordsTotal = 0, int $recordsFiltered = 0): JsonResponse
    {
        $recordsTotal = $recordsTotal ?? count($data);
        $recordsFiltered = $recordsFiltered ?? $recordsTotal;

        $response = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ];

        return new JsonResponse($response);
    }
}
