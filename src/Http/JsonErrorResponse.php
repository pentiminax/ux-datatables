<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class JsonErrorResponse
{
    public static function create(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
