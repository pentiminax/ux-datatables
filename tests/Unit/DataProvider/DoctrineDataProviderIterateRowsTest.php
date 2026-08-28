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
