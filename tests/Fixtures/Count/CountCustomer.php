<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_customer')]
class CountCustomer
{
    /** @var Collection<int, CountTag> */
    #[ORM\OneToMany(targetEntity: CountTag::class, mappedBy: 'customer', cascade: ['persist'])]
    public Collection $tags;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $name,
    ) {
        $this->tags = new ArrayCollection();
    }

    public function addTag(CountTag $tag): void
    {
        $tag->customer = $this;
        $this->tags->add($tag);
    }
}
