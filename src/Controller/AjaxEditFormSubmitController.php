<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Http\JsonErrorResponse;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxEditFormSubmitController
{
    public function __construct(
        private readonly EditFormService $service,
        private readonly MutationTokenValidator $tokenValidator,
        private readonly AjaxDataTableRegistry $registry,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEditFormRequestDto $payload): Response
    {
        $this->tokenValidator->validate($request);

        $result = $this->service->handleSubmit(
            $this->registry->resolveAction($payload->dataTable),
            $payload->id,
            $payload->formData,
        );

        if (!$result->success) {
            if (null !== $result->html) {
                return new JsonResponse([
                    'success' => false,
                    'html'    => $result->html,
                ], $result->statusCode);
            }

            return JsonErrorResponse::create($result->message, $result->statusCode);
        }

        return new JsonResponse(['success' => true]);
    }
}
