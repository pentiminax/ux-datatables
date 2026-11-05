<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\DetailRowService;
use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Ajax\RowActionRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxDetailController
{
    public function __construct(
        private readonly DetailRowService $service,
        private readonly RowActionGate $gate,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEntityQueryDto $payload): Response
    {
        $row = $this->gate->authorize($request, RowActionRequest::detail($payload->dataTable, $payload->id));

        return $this->service->render($row)->toJsonResponse();
    }
}
