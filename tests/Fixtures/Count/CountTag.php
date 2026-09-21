<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_tag')]
class CountTag
{
    #[ORM\ManyToOne(targetEntity: CountCustomer::class, inversedBy: 'tags')]
    public ?CountCustomer $customer = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $label,
    ) {
    }
}
