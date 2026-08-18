<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Exception\InvalidDataTableTokenException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

/**
 * A DataTable derived from a signed action token, together with the entity it
 * operates on. Every entity-scoped Ajax route resolves one of these instead of
 * trusting a client-supplied entity or DataTable class name.
 */
final readonly class ResolvedDataTable
{
    /**
     * @param class-string|null               $entityClass
     * @param class-string<AbstractDataTable> $dataTableClass
     */
    public function __construct(
        public AbstractDataTable $table,
        public ?string $entityClass,
        public string $dataTableClass,
    ) {
    }

    /**
     * @return class-string
     *
     * @throws InvalidDataTableTokenException when the table declares no entity class to operate on
     */
    public function requireEntityClass(): string
    {
        return $this->entityClass ?? throw InvalidDataTableTokenException::missingEntityClass($this->dataTableClass);
    }
}
