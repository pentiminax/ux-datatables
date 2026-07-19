<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

final readonly class BooleanMutationContext
{
    /**
     * @param class-string $entityClass
     * @param class-string $dataTableClass
     */
    public function __construct(
        public string $entityClass,
        public string $dataTableClass,
        public string $field,
    ) {
    }
}
