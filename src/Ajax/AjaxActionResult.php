<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class AjaxActionResult
{
    private function __construct(
        public bool $success,
        public ?string $html,
        public string $message,
        public int $statusCode,
    ) {
    }

    public static function success(?string $html = null): self
    {
        return new self(
            success: true,
            html: $html,
            message: '',
            statusCode: Response::HTTP_OK,
        );
    }

    public static function badRequest(string $message): self
    {
        return new self(
            success: false,
            html: null,
            message: $message,
            statusCode: Response::HTTP_BAD_REQUEST,
        );
    }

    public static function invalid(string $html): self
    {
        return new self(
            success: false,
            html: $html,
            message: '',
            statusCode: Response::HTTP_OK,
        );
    }

    public static function notFound(): self
    {
        return new self(
            success: false,
            html: null,
            message: 'Entity not found.',
            statusCode: Response::HTTP_NOT_FOUND,
        );
    }

    public static function forbidden(string $message = 'You are not allowed to perform this action.'): self
    {
        return new self(
            success: false,
            html: null,
            message: $message,
            statusCode: Response::HTTP_FORBIDDEN,
        );
    }

    public function toJsonResponse(): JsonResponse
    {
        if (!$this->success) {
            if (null !== $this->html) {
                return new JsonResponse([
                    'success' => false,
                    'html'    => $this->html,
                ], $this->statusCode);
            }

            return new JsonResponse([
                'success' => false,
                'message' => $this->message,
            ], $this->statusCode);
        }

        $payload = ['success' => true];
        if (null !== $this->html) {
            $payload['html'] = $this->html;
        }

        return new JsonResponse($payload);
    }
}
