<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'count_event')]
class CountEvent
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $name,
        #[ORM\Column(type: 'datetime_immutable')]
        public \DateTimeImmutable $at,
        #[ORM\Column(type: 'boolean')]
        public bool $active = true,
        #[ORM\Column(type: 'string', nullable: true)]
        public ?string $note = null,
    ) {
    }
}
