<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'related_toggle_order')]
class RelatedToggleOrder
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $label,
        #[ORM\ManyToOne(targetEntity: RelatedToggleCustomer::class)]
        public RelatedToggleCustomer $customer,
    ) {
    }
}
