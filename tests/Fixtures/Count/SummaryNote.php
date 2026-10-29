<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'summary_note')]
class SummaryNote
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $text,
        #[ORM\ManyToOne(targetEntity: SummaryOrder::class, inversedBy: 'notes')]
        public ?SummaryOrder $order = null,
    ) {
    }
}
