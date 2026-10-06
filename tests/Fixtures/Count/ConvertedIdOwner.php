<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_converted_id_owner')]
class ConvertedIdOwner
{
    /** @var Collection<int, ConvertedIdItem> */
    #[ORM\OneToMany(targetEntity: ConvertedIdItem::class, mappedBy: 'owner', cascade: ['persist'])]
    public Collection $items;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: PrefixedIdType::NAME)]
        public PrefixedId $id,
        #[ORM\Column(type: 'string')]
        public string $name,
    ) {
        $this->items = new ArrayCollection();
    }

    public function addItem(ConvertedIdItem $item): void
    {
        $item->owner = $this;
        $this->items->add($item);
    }
}
