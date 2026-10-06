<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_string_key_item')]
class StringKeyItem
{
    #[ORM\ManyToOne(targetEntity: StringKeyOwner::class, inversedBy: 'items')]
    #[ORM\JoinColumn(referencedColumnName: 'id')]
    public ?StringKeyOwner $owner = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
    ) {
    }
}
