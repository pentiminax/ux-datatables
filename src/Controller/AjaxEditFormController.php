<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Http\JsonErrorResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxEditFormController
{
    public function __construct(
        private readonly EditFormService $service,
        private readonly AjaxDataTableRegistry $registry,
    ) {
    }

    public function __invoke(#[MapRequestPayload] AjaxEditFormQueryDto $payload): Response
    {
        $result = $this->service->handleView($this->registry->resolveAction($payload->dataTable), $payload->id);

        if (!$result->success) {
            return JsonErrorResponse::create($result->message, $result->statusCode);
        }

        return new JsonResponse([
            'success' => true,
            'html'    => $result->html,
        ]);
    }
}
