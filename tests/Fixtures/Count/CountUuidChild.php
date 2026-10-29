<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_uuid_child')]
class CountUuidChild
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\ManyToOne(targetEntity: CountUuidOwner::class)]
        public ?CountUuidOwner $owner = null,
    ) {
    }
}
