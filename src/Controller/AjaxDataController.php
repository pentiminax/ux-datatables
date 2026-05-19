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

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->query->get('table');

        if (!\is_string($token) || '' === $token) {
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
