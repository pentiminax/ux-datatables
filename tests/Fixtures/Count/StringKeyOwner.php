<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A VARCHAR primary key, where "01" and "1" are two different rows.
 */
#[ORM\Entity]
#[ORM\Table(name: 'count_string_key_owner')]
class StringKeyOwner
{
    /** @var Collection<int, StringKeyItem> */
    #[ORM\OneToMany(targetEntity: StringKeyItem::class, mappedBy: 'owner', cascade: ['persist'])]
    public Collection $items;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        public string $id,
    ) {
        $this->items = new ArrayCollection();
    }

    public function addItem(StringKeyItem $item): void
    {
        $item->owner = $this;
        $this->items->add($item);
    }
}
