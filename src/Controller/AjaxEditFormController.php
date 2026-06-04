<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Http\JsonErrorResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

final class AjaxEditFormController
{
    public function __construct(
        private readonly EditFormService $service,
    ) {
    }

    public function __invoke(#[MapQueryString] AjaxEditFormQueryDto $payload): Response
    {
        $result = $this->service->handleView($payload);

        if (!$result->success) {
            return JsonErrorResponse::create($result->message, $result->statusCode);
        }

        return new JsonResponse([
            'success' => true,
            'html'    => $result->html,
        ]);
    }
}
