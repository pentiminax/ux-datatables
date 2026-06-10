<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Detail;

use Symfony\Component\HttpFoundation\Response;

final readonly class DetailRowResult
{
    private function __construct(
        public bool $success,
        public ?string $html,
        public string $message,
        public int $statusCode,
    ) {
    }

    public static function success(string $html): self
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

    public static function notFound(): self
    {
        return new self(
            success: false,
            html: null,
            message: 'Entity not found.',
            statusCode: Response::HTTP_NOT_FOUND,
        );
    }
}
