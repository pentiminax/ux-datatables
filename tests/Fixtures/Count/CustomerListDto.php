<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

/**
 * Projected read-model for a CountCustomer, exposing a computed display value.
 */
final readonly class CustomerListDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $badge,
    ) {
    }
}
