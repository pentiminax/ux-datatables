<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

abstract class MutationException extends \RuntimeException
{
    abstract public function getStatusCode(): int;

    public function getClientMessage(): string
    {
        return $this->getMessage();
    }
}
