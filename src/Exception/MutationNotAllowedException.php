<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class MutationNotAllowedException extends MutationException
{
    public function __construct(string $message = 'You are not allowed to perform this action.')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}
