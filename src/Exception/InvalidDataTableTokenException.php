<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class InvalidDataTableTokenException extends MutationException
{
    public function getStatusCode(): int
    {
        return 400;
    }

    public static function invalidToken(): self
    {
        return new self('Invalid DataTable token.');
    }

    public static function missingEntityClass(string $dataTableClass): self
    {
        return new self(\sprintf('DataTable "%s" must define an entity class.', $dataTableClass));
    }
}
