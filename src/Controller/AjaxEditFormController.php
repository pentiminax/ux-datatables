<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Ajax\RowActionRequest;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxEditFormController
{
    public function __construct(
        private readonly EditFormService $service,
        private readonly RowActionGate $gate,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEntityQueryDto $payload): Response
    {
        $row = $this->gate->authorize($request, RowActionRequest::editFormView($payload->dataTable, $payload->id));

        return $this->service->renderForm($row)->toJsonResponse();
    }
}
