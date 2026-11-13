<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Answers a DataTables Ajax request for a table from a custom endpoint, with Web Profiler collection.
 */
final class DataTableResponder
{
    /**
     * Returns the DataTables JSON response, or null when the request carries no DataTables payload.
     */
    public function respond(AbstractDataTable $table, Request $request): ?JsonResponse
    {
        $table->handleDataTableRequest($request);

        return $table->isDataTableRequestHandled() ? $table->createDataTableResponse() : null;
    }
}
