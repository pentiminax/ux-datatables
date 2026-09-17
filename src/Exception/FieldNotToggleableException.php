<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class FieldNotToggleableException extends MutationException
{
    public function __construct(string $field)
    {
        parent::__construct(\sprintf('The field "%s" is not a toggleable boolean field.', $field));
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}
