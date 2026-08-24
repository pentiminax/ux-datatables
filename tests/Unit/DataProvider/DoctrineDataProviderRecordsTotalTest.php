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
 * recordsTotal used to run through a completely separate, unconfigured
 * "COUNT(e) FROM Entity e" query that never called configureQueryBuilder() at all. A
 * permanent, business-rule WHERE clause added via AbstractDataTable::customizeQueryBuilder()
 * (e.g. "only active rows") correctly excluded those rows from the page and from
 * recordsFiltered, but recordsTotal still counted every row in the underlying table --
 * including ones the app never intends to be visible at all. DataTables would then show
 * something like "2 of 3 entries" as if a user search hid a real row, when that row is
 * permanently excluded by business rule and should never be counted in the first place.
 *
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderRecordsTotalTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class, CountTag::class);

        $this->em->persist(new CountCustomer(1, 'Alpha'));
        $this->em->persist(new CountCustomer(2, 'Beta'));
        $this->em->persist(new CountCustomer(3, 'Gamma'));
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function records_total_respects_the_base_query_builder_customization(): void
    {
        $excludeBeta = static fn (QueryBuilder $qb): QueryBuilder => $qb->andWhere("e.name != 'Beta'");

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->rowMapper(),
            configureQueryBuilder: $excludeBeta,
            configureBaseQueryBuilder: $excludeBeta,
        );

        $result = $provider->fetchData($this->request());

        $this->assertSame(2, $result->recordsTotal);
        $this->assertSame(2, $result->recordsFiltered);
    }

    /**
     * recordsTotal must reflect the app's own permanent scoping, but never the request's
     * own interactive search/filter terms -- that distinction is what recordsFiltered
     * exists to report. configureBaseQueryBuilder only ever receives
     * customizeQueryBuilder() (see AbstractDataTable::runtime()), never the interactive
     * pipeline, so simulating "an interactive filter applied only to the full query" here
     * proves recordsTotal stays unaffected by it.
     */
    #[Test]
    public function records_total_is_unaffected_by_a_filter_applied_only_to_the_full_query_builder(): void
    {
        $interactiveSearchOnly = static fn (QueryBuilder $qb): QueryBuilder => $qb->andWhere("e.name = 'Alpha'");

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->rowMapper(),
            configureQueryBuilder: $interactiveSearchOnly,
            // configureBaseQueryBuilder intentionally omitted: an interactive search term
            // narrows the full query only, never the base query recordsTotal counts.
        );

        $result = $provider->fetchData($this->request());

        $this->assertSame(3, $result->recordsTotal);
        $this->assertSame(1, $result->recordsFiltered);
    }

    #[Test]
    public function records_total_counts_every_row_without_a_base_query_builder_customization(): void
    {
        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->rowMapper(),
        );

        $result = $provider->fetchData($this->request());

        $this->assertSame(3, $result->recordsTotal);
        $this->assertSame(3, $result->recordsFiltered);
    }

    private function rowMapper(): RowMapperInterface
    {
        return new class implements RowMapperInterface {
            public function map(mixed $row): array
            {
                return ['id' => $row->id];
            }
        };
    }

    private function request(): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: 0, length: 10);
    }
}
