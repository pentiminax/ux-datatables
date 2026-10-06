<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\CollectionJoinDetector;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CollectionJoinDetector::class)]
final class CollectionJoinDetectorTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class, CountTag::class);
    }

    #[Test]
    public function it_reports_no_collection_for_a_query_without_joins(): void
    {
        $this->assertFalse($this->detect($this->tags()));
    }

    #[Test]
    public function it_reports_no_collection_for_a_single_valued_join(): void
    {
        $this->assertFalse($this->detect($this->tags()->leftJoin('e.customer', 'c')));
    }

    #[Test]
    public function it_reports_a_collection_join_on_the_root(): void
    {
        $this->assertTrue($this->detect($this->customers()->leftJoin('e.tags', 't')));
    }

    #[Test]
    public function it_follows_an_alias_to_a_collection_behind_a_single_valued_join(): void
    {
        $qb = $this->tags()
            ->leftJoin('e.customer', 'c')
            ->leftJoin('c.tags', 'other_tags');

        $this->assertTrue($this->detect($qb));
    }

    #[Test]
    public function it_resolves_joins_declared_before_the_alias_they_hang_from(): void
    {
        $qb = $this->tags()
            ->leftJoin('c.tags', 'other_tags')
            ->leftJoin('e.customer', 'c');

        $this->assertTrue($this->detect($qb));
    }

    #[Test]
    public function it_treats_an_entity_class_join_as_multiplying(): void
    {
        $qb = $this->customers()->leftJoin(CountTag::class, 't', 'WITH', 't.customer = e');

        $this->assertTrue($this->detect($qb));
    }

    #[Test]
    public function it_treats_a_join_on_an_undeclared_alias_as_multiplying(): void
    {
        $this->assertTrue($this->detect($this->customers()->leftJoin('ghost.tags', 't')));
    }

    private function detect(QueryBuilder $qb): bool
    {
        $root = $qb->getRootEntities()[0];

        return CollectionJoinDetector::joinsCollection($qb, $this->em, 'e', $root);
    }

    private function customers(): QueryBuilder
    {
        return $this->em->createQueryBuilder()->select('e')->from(CountCustomer::class, 'e');
    }

    private function tags(): QueryBuilder
    {
        return $this->em->createQueryBuilder()->select('e')->from(CountTag::class, 'e');
    }
}
