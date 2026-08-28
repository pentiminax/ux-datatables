<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Export\ExportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AjaxExportController
{
    public function __construct(
        private readonly AjaxDataTableRegistry $registry,
        private readonly ExportService $exportService,
    ) {
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $token = $request->query->getString('table');

        if ('' === $token) {
            throw new NotFoundHttpException('DataTable not found.');
        }

        $table = $this->registry->get($token);

        if (null === $table) {
            throw new NotFoundHttpException('DataTable not found.');
        }

        return $this->exportService->export($table, $request);
    }
}
