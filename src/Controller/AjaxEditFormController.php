<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Form\EditFormViewHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

final class AjaxEditFormController
{
    public function __construct(
        private readonly EditFormViewHandler $viewHandler,
    ) {
    }

    public function __invoke(#[MapQueryString] AjaxEditFormQueryDto $payload): Response
    {
        $result = $this->viewHandler->handle($payload);

        if (!$result->success) {
            return $this->jsonError($result->message, $result->statusCode);
        }

        return new JsonResponse([
            'success' => true,
            'html'    => $result->html,
        ]);
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
