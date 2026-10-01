<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

/**
 * A row identified by a composite key.
 *
 * The default `id` cannot be remapped onto two identifiers, so bulk actions must refuse it
 * instead of letting Doctrine fail on an unknown field.
 */
#[ORM\Entity]
#[ORM\Table(name: 'count_line')]
class CountLine
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $orderId,
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $lineNo,
        #[ORM\Column(type: 'string')]
        public string $name,
    ) {
    }
}
