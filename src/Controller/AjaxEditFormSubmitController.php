<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Ajax\RowActionRequest;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxEditFormSubmitController
{
    public function __construct(
        private readonly EditFormService $service,
        private readonly RowActionGate $gate,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEditFormRequestDto $payload): Response
    {
        $row = $this->gate->authorize($request, RowActionRequest::editFormSubmit($payload->dataTable, $payload->id));

        return $this->service->submitForm($row, $payload->formData)->toJsonResponse();
    }
}
