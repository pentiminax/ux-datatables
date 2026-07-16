<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Rehydration;

final class SourceRowMap
{
    /**
     * @param array<string, object> $entities
     */
    public function __construct(
        private readonly array $entities,
        private readonly RowIdentifierExtractor $identifierExtractor,
    ) {
    }

    public static function empty(RowIdentifierExtractor $extractor): self
    {
        return new self([], $extractor);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function sourceFor(array $row): ?object
    {
        $id = $this->identifierExtractor->extract($row);

        return null !== $id ? ($this->entities[(string) $id] ?? null) : null;
    }
}
