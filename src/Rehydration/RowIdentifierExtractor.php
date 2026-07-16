<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Rehydration;

final class RowIdentifierExtractor
{
    /**
     * @param array<string, mixed> $row
     */
    public function extract(array $row): int|string|null
    {
        $id = $row['id'] ?? null;
        if (\is_string($id) || \is_int($id)) {
            return $id;
        }

        $iri = $row['@id'] ?? null;
        if (!\is_string($iri) || '' === trim($iri)) {
            return null;
        }

        $lastSegment = basename(parse_url($iri, \PHP_URL_PATH) ?: '');

        return '' === $lastSegment ? null : $lastSegment;
    }
}
