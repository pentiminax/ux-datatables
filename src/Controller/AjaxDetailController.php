<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\DetailRowService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxDetailController
{
    public function __construct(
        private readonly DetailRowService $service,
        private readonly AjaxDataTableRegistry $registry,
    ) {
    }

    public function __invoke(#[MapRequestPayload] AjaxEntityQueryDto $payload): Response
    {
        $result = $this->service->handleView($this->registry->resolveAction($payload->dataTable), $payload->id);

        return $result->toJsonResponse();
    }
}
