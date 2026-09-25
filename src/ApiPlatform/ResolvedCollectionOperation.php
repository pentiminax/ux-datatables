<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\Operation;

/**
 * The single collection operation a DataTable reads from, with the path it is served at.
 */
final readonly class ResolvedCollectionOperation
{
    public function __construct(
        public string $url,
        public Operation $operation,
    ) {
    }
}
