<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CustomerListDto;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderIterateRowsTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class, CountTag::class);

        $this->em->persist(new CountCustomer(1, 'Alpha'));
        $this->em->persist(new CountCustomer(2, 'Beta'));
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_iterates_every_filtered_row_ignoring_page_length(): void
    {
        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
        );

        $rows = iterator_to_array($provider->iterateRows($this->request(length: 1)), false);

        $this->assertSame([['id' => 1], ['id' => 2]], $rows);
    }

    #[Test]
    public function it_reuses_the_query_builder_pipeline_without_limit_or_count(): void
    {
        $seenQb = null;

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            configureQueryBuilder: static function (QueryBuilder $qb) use (&$seenQb): QueryBuilder {
                $seenQb = $qb;

                return $qb->andWhere('e.name = :name')->setParameter('name', 'Alpha');
            },
        );

        $rows = iterator_to_array($provider->iterateRows($this->request(length: 1)), false);

        $this->assertSame([['id' => 1]], $rows);
        $this->assertInstanceOf(QueryBuilder::class, $seenQb);
        $this->assertNull($seenQb->getMaxResults());
        $this->assertSame(0, $seenQb->getFirstResult() ?? 0);
        $this->assertStringNotContainsStringIgnoringCase('COUNT(', $seenQb->getQuery()->getSQL());
    }

    #[Test]
    public function it_projects_rows_in_chunks(): void
    {
        $chunkSizes = [];

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['id' => $row instanceof RowContext ? $row->item->id : $row->id];
                }
            },
            pageProjector: function (array $items) use (&$chunkSizes): array {
                $chunkSizes[] = \count($items);

                return array_map(
                    static fn (CountCustomer $customer): CustomerListDto => new CustomerListDto(
                        $customer->id,
                        $customer->name,
                        'BADGE:'.$customer->name,
                    ),
                    $items,
                );
            },
            exportChunkSize: 1,
        );

        $rows = iterator_to_array($provider->iterateRows($this->request()), false);

        $this->assertSame([['id' => 1], ['id' => 2]], $rows);
        $this->assertSame([1, 1], $chunkSizes);
    }

    /**
     * The documented contract: a projector deriving each item from itself returns the same rows
     * whatever the batch size, so an export never depends on exportChunkSize. A projector deriving
     * values from the batch's composition is out of contract -- see AbstractDataTable::projectPage().
     */
    #[Test]
    public function it_projects_the_same_values_whatever_the_chunk_size(): void
    {
        $rowsByChunkSize = [];

        foreach ([1, 2, 250] as $chunkSize) {
            $provider = new DoctrineDataProvider(
                em: $this->em,
                entityClass: CountCustomer::class,
                rowMapper: new class implements RowMapperInterface {
                    public function map(mixed $row): array
                    {
                        return ['badge' => $row instanceof RowContext ? $row->item->badge : null];
                    }
                },
                pageProjector: static fn (array $items): array => array_map(
                    static fn (CountCustomer $customer): CustomerListDto => new CustomerListDto(
                        $customer->id,
                        $customer->name,
                        'BADGE:'.$customer->name,
                    ),
                    $items,
                ),
                exportChunkSize: $chunkSize,
            );

            $rowsByChunkSize[$chunkSize] = iterator_to_array($provider->iterateRows($this->request()), false);
        }

        $expected = [['badge' => 'BADGE:Alpha'], ['badge' => 'BADGE:Beta']];

        $this->assertSame($expected, $rowsByChunkSize[1]);
        $this->assertSame($expected, $rowsByChunkSize[2]);
        $this->assertSame($expected, $rowsByChunkSize[250]);
    }

    #[Test]
    public function it_releases_exported_entities_without_clearing_the_entity_manager(): void
    {
        $bystander = new CountTag(1, 'vip');
        $this->em->persist($bystander);
        $this->em->flush();

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            exportChunkSize: 1,
        );

        iterator_to_array($provider->iterateRows($this->request()), false);

        // clear() used to run here, which would detach every managed entity -- including ones the
        // rest of the request still depends on, such as the security token's own User.
        $this->assertTrue($this->em->contains($bystander));
    }

    #[Test]
    public function it_maps_exported_rows_with_the_export_mapper_when_one_is_configured(): void
    {
        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            exportRowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['name' => $row instanceof RowContext ? $row->item->name : $row->name];
                }
            },
        );

        $this->assertSame(
            [['name' => 'Alpha'], ['name' => 'Beta']],
            iterator_to_array($provider->iterateRows($this->request()), false),
        );
        $this->assertSame(
            [['id' => 1], ['id' => 2]],
            iterator_to_array($provider->fetchData($this->request())->data, false),
            'The displayed table keeps its own mapper',
        );
    }

    /**
     * Searching or scoping through a to-many association (tags.label, order items, ...) adds a
     * LEFT JOIN. fetchData() hydrates unique roots through getResult(); toIterable() throws
     * QueryException for that same DQL, which used to abort an export after its download headers
     * were already sent.
     */
    #[Test]
    public function it_exports_unique_roots_when_a_to_many_join_multiplies_sql_rows(): void
    {
        $this->seedTags();

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            configureQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->leftJoin('e.tags', 't')
                ->addOrderBy('e.id', 'ASC'),
            exportChunkSize: 1,
        );

        $request  = $this->request();
        $fetched  = iterator_to_array($provider->fetchData($request)->data, false);
        $exported = iterator_to_array($provider->iterateRows($request), false);

        $this->assertSame([['id' => 1], ['id' => 2]], $fetched);
        $this->assertSame($fetched, $exported);
    }

    #[Test]
    public function it_exports_fetch_joined_collections_without_throwing(): void
    {
        $this->seedTags();

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            configureQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->leftJoin('e.tags', 't')
                ->addSelect('t')
                ->addOrderBy('e.id', 'ASC'),
        );

        $exported = iterator_to_array($provider->iterateRows($this->request()), false);

        $this->assertSame([['id' => 1], ['id' => 2]], $exported);
    }

    #[Test]
    public function it_exports_a_grouped_permanent_scope_without_throwing(): void
    {
        $this->seedTags();
        $gamma = new CountCustomer(3, 'Gamma');
        $gamma->addTag(new CountTag(30, 'red'));
        $gamma->addTag(new CountTag(31, 'green'));
        $this->em->persist($gamma);
        $this->em->flush();
        $this->em->clear();

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            configureQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->leftJoin('e.tags', 't')
                ->groupBy('e.id')
                ->having('COUNT(t.id) > 1')
                ->addOrderBy('e.id', 'ASC'),
        );

        $exported = iterator_to_array($provider->iterateRows($this->request()), false);

        $this->assertSame([['id' => 1], ['id' => 3]], $exported);
    }

    /**
     * Ordering on a non-unique column leaves ties the database may return in a different order
     * from one query to the next; the export appends the identifier so every chunk sees the same
     * total order and no row is exported twice or skipped.
     */
    #[Test]
    public function it_exports_every_row_once_when_the_ordering_column_has_ties(): void
    {
        $this->em->persist(new CountCustomer(3, 'Alpha'));
        $this->em->persist(new CountCustomer(4, 'Alpha'));
        $this->em->flush();
        $this->em->clear();

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            configureQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->addOrderBy('e.name', 'ASC'),
            exportChunkSize: 1,
        );

        $exported = iterator_to_array($provider->iterateRows($this->request()), false);

        $this->assertSame([['id' => 1], ['id' => 3], ['id' => 4], ['id' => 2]], $exported);
    }

    /**
     * A LIMIT/OFFSET set by customizeQueryBuilder() caps the export. It counts root entities: the
     * to-many join multiplies SQL rows, so a LIMIT left on the query itself would have cut the
     * export short.
     */
    #[Test]
    public function it_honors_a_window_set_on_the_query_builder(): void
    {
        $this->seedTags();

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            configureQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->leftJoin('e.tags', 't')
                ->addOrderBy('e.id', 'ASC')
                ->setFirstResult(1)
                ->setMaxResults(1),
        );

        $exported = iterator_to_array($provider->iterateRows($this->request()), false);

        $this->assertSame([['id' => 2]], $exported);
    }

    private function seedTags(): void
    {
        $alpha = $this->em->find(CountCustomer::class, 1);
        $beta  = $this->em->find(CountCustomer::class, 2);
        \assert($alpha instanceof CountCustomer);
        \assert($beta instanceof CountCustomer);

        $alpha->addTag(new CountTag(10, 'red'));
        $alpha->addTag(new CountTag(11, 'green'));
        $alpha->addTag(new CountTag(12, 'blue'));
        $beta->addTag(new CountTag(20, 'red'));

        $this->em->flush();
        $this->em->clear();
    }

    private function identityMapper(): RowMapperInterface
    {
        return new class implements RowMapperInterface {
            public function map(mixed $row): array
            {
                return ['id' => $row instanceof RowContext ? $row->item->id : $row->id];
            }
        };
    }

    private function request(int $length = 10): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: 0, length: $length);
    }
}
