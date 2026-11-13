<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Runtime\DataTableResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AjaxDataController
{
    public function __construct(
        private readonly AjaxDataTableRegistry $registry,
        private readonly DataTableResponder $responder = new DataTableResponder(),
    ) {
    }

    /**
     * Profiler collection happens inside the table's response builder, not here -- that way a
     * custom endpoint using {@see DataTableResponder} (bypassing this controller entirely) still
     * gets profiled, which this controller alone could never guarantee.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->query->getString('table');

        if ('' === $token) {
            throw new NotFoundHttpException('DataTable not found.');
        }

        $table = $this->registry->get($token);

        if (null === $table) {
            throw new NotFoundHttpException('DataTable not found.');
        }

        return $this->responder->respond($table, $request) ?? throw new BadRequestHttpException('Invalid DataTables request.');
    }
}
