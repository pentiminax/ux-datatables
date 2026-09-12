<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Query\SearchConditionBuilder;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the generated LIKE condition against a real database.
 *
 * sqlite's LIKE is already case-insensitive for ASCII, so this does not prove the LOWER()
 * behavior on PostgreSQL; what it does prove is that the generated DQL is valid and that
 * lowercasing the bound term does not break matching.
 *
 * @internal
 */
#[CoversClass(SearchConditionBuilder::class)]
final class SearchConditionBuilderExecutionTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class);

        $this->em->persist(new CountCustomer(1, 'Alice'));
        $this->em->persist(new CountCustomer(2, 'Bob'));
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_matches_regardless_of_case_on_sqlite(): void
    {
        $qb = $this->em->createQueryBuilder()->select('e')->from(CountCustomer::class, 'e');

        $qb->andWhere(SearchConditionBuilder::text($qb, 'e', 'name', 'ALI', 'param_0'));

        $names = array_map(static fn (CountCustomer $c): string => $c->name, $qb->getQuery()->getResult());

        $this->assertSame(['Alice'], $names);
    }
}
