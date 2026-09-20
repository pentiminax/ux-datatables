<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * When customizeQueryBuilder() adds GROUP BY/HAVING as permanent dataset scoping (e.g.
 * grouping by a related entity and using HAVING to keep only groups matching an aggregate
 * condition), the count queries used to clone that query builder, replace the select with
 * COUNT(DISTINCT e), and call getSingleScalarResult() -- but a clone still carries the
 * GROUP BY, so the count query returned one scalar per group instead of one overall total,
 * and getSingleScalarResult() threw NonUniqueResultException as soon as more than one group
 * qualified.
 *
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderGroupedCountTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class, CountTag::class);

        // Three customers with a varying number of tags: grouping by customer and keeping
        // only groups with more than one tag (HAVING COUNT(t) > 1) qualifies two of them.
        $alpha = new CountCustomer(1, 'Alpha');
        $alpha->addTag(new CountTag(10, 'red'));
        $alpha->addTag(new CountTag(11, 'green'));

        $beta = new CountCustomer(2, 'Beta');
        $beta->addTag(new CountTag(20, 'red'));

        $gamma = new CountCustomer(3, 'Gamma');
        $gamma->addTag(new CountTag(30, 'red'));
        $gamma->addTag(new CountTag(31, 'green'));
        $gamma->addTag(new CountTag(32, 'blue'));

        $this->em->persist($alpha);
        $this->em->persist($beta);
        $this->em->persist($gamma);
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function records_total_and_filtered_count_handle_a_grouped_permanent_scope_without_throwing(): void
    {
        $groupByCustomersWithMultipleTags = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.tags', 't')
            ->groupBy('e.id')
            ->having('COUNT(t.id) > 1');

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['id' => $row->id];
                }
            },
            configureQueryBuilder: $groupByCustomersWithMultipleTags,
            configureBaseQueryBuilder: $groupByCustomersWithMultipleTags,
        );

        $result = $provider->fetchData($this->request());

        // Alpha (2 tags) and Gamma (3 tags) qualify; Beta (1 tag) does not.
        $this->assertSame(2, $result->recordsTotal);
        $this->assertSame(2, $result->recordsFiltered);
        $this->assertCount(2, iterator_to_array($result->data));
    }

    private function request(): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: 0, length: 10);
    }
}
