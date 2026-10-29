<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_uuid_owner')]
class CountUuidOwner
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        public string $id,
        #[ORM\Column(type: 'string')]
        public string $name,
    ) {
    }
}
