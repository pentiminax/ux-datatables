<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'summary_order')]
class SummaryOrder
{
    /** @var Collection<int, SummaryNote> */
    #[ORM\OneToMany(targetEntity: SummaryNote::class, mappedBy: 'order', cascade: ['persist'])]
    public Collection $notes;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $status,
        #[ORM\Column(type: 'integer', nullable: true)]
        public ?int $amount,
        #[ORM\ManyToOne(targetEntity: SummaryRegion::class, cascade: ['persist'])]
        public ?SummaryRegion $region = null,
    ) {
        $this->notes = new ArrayCollection();
    }

    public function addNote(string $text): void
    {
        $this->notes->add(new SummaryNote($this->id * 100 + $this->notes->count(), $text, $this));
    }
}
