<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_binary_id_owner')]
class BinaryIdOwner
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: BinaryIdType::NAME, length: 16)]
        public string $id,
        #[ORM\Column(type: 'string')]
        public string $name,
    ) {
    }
}
