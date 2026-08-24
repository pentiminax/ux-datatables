<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AjaxDataController
{
    public function __construct(
        private readonly AjaxDataTableRegistry $registry,
    ) {
    }

    /**
     * Profiler collection happens inside {@see AbstractDataTable::getResponse()} itself, not
     * here -- that way a custom ajax() endpoint calling handleRequest()/getResponse() directly
     * (bypassing this controller entirely) still gets profiled, which this controller alone
     * could never guarantee.
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

        $table->handleRequest($request);

        if (!$table->isRequestHandled()) {
            throw new BadRequestHttpException('Invalid DataTables request.');
        }

        return $table->getResponse();
    }
}
