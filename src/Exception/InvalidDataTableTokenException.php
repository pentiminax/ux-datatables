<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class InvalidDataTableTokenException extends MutationException
{
    public function __construct(string $message, private readonly ?string $clientMessage = null)
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 400;
    }

    /**
     * The technical message names the DataTable for the logs; the client never sees the FQCN.
     */
    public function getClientMessage(): string
    {
        return $this->clientMessage ?? $this->getMessage();
    }

    public static function invalidToken(): self
    {
        return new self('Invalid DataTable token.');
    }

    public static function missingEntityClass(string $dataTableClass): self
    {
        return new self(
            \sprintf('DataTable "%s" must define an entity class.', $dataTableClass),
            'This DataTable does not support entity mutations.',
        );
    }
}
