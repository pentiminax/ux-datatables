<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Doctrine\Persistence\ObjectManager;

final readonly class EditFormEntityContext
{
    /**
     * @param string[] $identifierFields
     */
    public function __construct(
        public object $entity,
        public ObjectManager $manager,
        public array $identifierFields,
    ) {
    }
}
