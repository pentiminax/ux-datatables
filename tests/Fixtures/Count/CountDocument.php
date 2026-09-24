<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

/**
 * A row whose public `id` is not the primary key.
 *
 * Bulk selection speaks in `DT_RowId`, which the runtime remaps from the default `id` field
 * onto the real identifier. Looking the batch up by the leftover `id` column would hit the
 * wrong rows whenever those values overlap the primary key space.
 */
#[ORM\Entity]
#[ORM\Table(name: 'count_document')]
class CountDocument
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $pk,
        #[ORM\Column(type: 'integer')]
        public int $id,
        #[ORM\Column(type: 'string')]
        public string $name,
    ) {
    }
}
