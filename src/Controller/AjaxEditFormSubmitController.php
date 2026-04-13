<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxEditFormSubmitController
{
    public function __construct(
        private readonly EditFormService $service,
    ) {
    }

    public function __invoke(#[MapRequestPayload] AjaxEditFormRequestDto $payload): Response
    {
        $result = $this->service->handleSubmit($payload);

        if (!$result->success) {
            if (null !== $result->html) {
                return new JsonResponse([
                    'success' => false,
                    'html'    => $result->html,
                ], $result->statusCode);
            }

            return $this->jsonError($result->message, $result->statusCode);
        }

        return new JsonResponse(['success' => true]);
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
