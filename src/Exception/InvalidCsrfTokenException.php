<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class InvalidCsrfTokenException extends MutationException
{
    public function __construct(string $message = 'Invalid CSRF token.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}
