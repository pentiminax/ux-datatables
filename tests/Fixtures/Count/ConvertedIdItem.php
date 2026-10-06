<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_converted_id_item')]
class ConvertedIdItem
{
    #[ORM\ManyToOne(targetEntity: ConvertedIdOwner::class, inversedBy: 'items')]
    #[ORM\JoinColumn(referencedColumnName: 'id')]
    public ?ConvertedIdOwner $owner = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
    ) {
    }
}
