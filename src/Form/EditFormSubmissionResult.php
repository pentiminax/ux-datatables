<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Symfony\Component\HttpFoundation\Response;

final readonly class EditFormSubmissionResult
{
    private function __construct(
        public bool $success,
        public ?string $html,
        public string $message,
        public int $statusCode,
    ) {
    }

    public static function success(): self
    {
        return new self(
            success: true,
            html: null,
            message: '',
            statusCode: Response::HTTP_OK,
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
}
