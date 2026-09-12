<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

final class InvalidBooleanMutationContextException extends MutationException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $clientMessage = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return 400;
    }

    /**
     * The technical message names the DataTable for the logs; the client only learns which field
     * it asked about.
     */
    public function getClientMessage(): string
    {
        return $this->clientMessage ?? $this->getMessage();
    }

    public static function fieldNotSwitchable(string $field, string $dataTableClass): self
    {
        return new self(
            \sprintf('Field "%s" is not a switchable boolean column on DataTable "%s".', $field, $dataTableClass),
            clientMessage: \sprintf('Field "%s" is not a switchable boolean column.', $field),
        );
    }
}
