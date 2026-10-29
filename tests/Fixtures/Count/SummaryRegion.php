<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'summary_region')]
class SummaryRegion
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $name,
        #[ORM\Column(type: 'integer')]
        public int $quota = 0,
    ) {
    }
}
