<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
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

        return $result->toJsonResponse();
    }
}
