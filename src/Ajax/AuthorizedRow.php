<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Mutation\MutationContext;

/**
 * @internal
 */
final readonly class AuthorizedRow
{
    /**
     * @param class-string|null $entityClass the entity a toggle changes; null for the other kinds
     */
    public function __construct(
        public ResolvedDataTable $table,
        public ?Action $action,
        public MutationContext $context,
        public ?string $field = null,
        public ?string $entityClass = null,
    ) {
    }
}
