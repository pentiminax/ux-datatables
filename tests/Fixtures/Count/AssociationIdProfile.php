<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\ORM\Mapping as ORM;

/**
 * A row identified by an association (shared primary key), not a scalar field.
 */
#[ORM\Entity]
#[ORM\Table(name: 'count_association_id_profile')]
class AssociationIdProfile
{
    public function __construct(
        #[ORM\Id]
        #[ORM\OneToOne(targetEntity: AssociationIdOwner::class)]
        #[ORM\JoinColumn(nullable: false)]
        public AssociationIdOwner $owner,
        #[ORM\Column(type: 'string')]
        public string $bio,
    ) {
    }
}
