<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class EntityNotFoundException extends MutationException
{
    public function __construct(string $message = 'Entity not found.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 404;
    }
}
