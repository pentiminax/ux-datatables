<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class PropertyNotWritableException extends MutationException
{
    public function __construct(string $field)
    {
        parent::__construct(\sprintf('Unable to write "%s" on the entity.', $field));
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}
