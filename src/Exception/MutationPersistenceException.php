<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class MutationPersistenceException extends MutationException
{
    public function __construct(string $message = 'The operation could not be completed due to a data conflict.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return 409;
    }
}
