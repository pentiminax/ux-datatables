<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_invoice')]
class CountInvoice
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $number,
        #[ORM\ManyToOne(targetEntity: CountCustomer::class)]
        public ?CountCustomer $customer = null,
        #[ORM\ManyToOne(targetEntity: SummaryRegion::class)]
        public ?SummaryRegion $region = null,
    ) {
    }
}
