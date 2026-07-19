<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class InvalidBooleanMutationContextException extends MutationException
{
    public function getStatusCode(): int
    {
        return 400;
    }

    public static function invalidDataTableToken(): self
    {
        return new self('Invalid DataTable token.');
    }

    public static function missingEntityClass(string $dataTableClass): self
    {
        return new self(\sprintf('DataTable "%s" must define an entity class to mutate a boolean switch.', $dataTableClass));
    }

    public static function fieldNotSwitchable(string $field, string $dataTableClass): self
    {
        return new self(\sprintf('Field "%s" is not a switchable boolean column on DataTable "%s".', $field, $dataTableClass));
    }
}
