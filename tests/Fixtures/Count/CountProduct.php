<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_product')]
class CountProduct
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'integer')]
        public int $stock,
        #[ORM\Column(type: 'float')]
        public float $weight,
        #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
        public string $price,
    ) {
    }
}
