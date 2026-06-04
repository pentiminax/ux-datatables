<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Doctrine\Persistence\ObjectManager;

final readonly class MutationContext
{
    public function __construct(
        public object $entity,
        public ObjectManager $manager,
    ) {
    }
}
