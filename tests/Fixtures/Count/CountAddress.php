<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class CountAddress
{
    public function __construct(
        #[ORM\Column(type: 'string')]
        public string $city,
        #[ORM\Column(type: 'integer')]
        public int $zip = 0,
    ) {
    }
}
