<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\ScopedIdentifierProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountDocument;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderScopedIdentifiersTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class, CountTag::class, CountDocument::class);

        foreach (range(1, 1200) as $id) {
            $this->em->persist(new CountCustomer($id, $id % 2 ? 'odd' : 'even'));
        }

        $this->em->persist(new CountDocument(pk: 1, id: 900, name: 'public'));
        $this->em->persist(new CountDocument(pk: 2, id: 901, name: 'private'));

        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_is_a_scoped_identifier_provider(): void
    {
        $this->assertInstanceOf(ScopedIdentifierProviderInterface::class, $this->provider());
    }

    #[Test]
    public function it_returns_every_requested_id_when_the_table_has_no_scope(): void
    {
        $this->assertSame([3, 1, 2], $this->provider()->filterIdentifiersInScope($this->request(), [3, 1, 2], 'id'));
    }

    #[Test]
    public function it_keeps_only_the_ids_the_permanent_scope_contains_in_their_given_order(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name = :name')
            ->setParameter('name', 'odd'));

        $this->assertSame([5, 1], $provider->filterIdentifiersInScope($this->request(), [5, 2, 1, 4, 999999], 'id'));
    }

    #[Test]
    public function it_checks_every_chunk_of_a_large_selection(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name = :name')
            ->setParameter('name', 'even'));

        $inScope = $provider->filterIdentifiersInScope($this->request(), range(1, 1200), 'id');

        $this->assertCount(600, $inScope);
        $this->assertSame(2, $inScope[0]);
        $this->assertSame(1200, $inScope[599]);
    }

    #[Test]
    public function it_matches_ids_sent_as_strings_and_returns_them_unchanged(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name = :name')
            ->setParameter('name', 'odd'));

        $this->assertSame(['3'], $provider->filterIdentifiersInScope($this->request(), ['3', '4'], 'id'));
    }

    #[Test]
    public function it_ignores_the_ordering_and_the_extra_selects_of_the_scope(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->addSelect('(SELECT COUNT(t.id) FROM '.CountTag::class.' t WHERE t.customer = e) AS HIDDEN tagCount')
            ->orderBy('tagCount', 'DESC'));

        $this->assertSame([1, 2], $provider->filterIdentifiersInScope($this->request(), [1, 2], 'id'));
    }

    #[Test]
    public function it_counts_a_root_once_when_the_scope_joins_a_collection(): void
    {
        $customer = $this->em->find(CountCustomer::class, 1);
        $customer->addTag(new CountTag(1, 'a'));
        $customer->addTag(new CountTag(2, 'b'));
        $this->em->flush();
        $this->em->clear();

        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb->innerJoin('e.tags', 't'));

        $this->assertSame([1], $provider->filterIdentifiersInScope($this->request(), [1, 2], 'id'));
    }

    #[Test]
    public function it_checks_a_configured_identifier_field_other_than_the_primary_key(): void
    {
        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountDocument::class,
            rowMapper: $this->mapper(),
            configureBaseQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->andWhere('e.name = :name')
                ->setParameter('name', 'public'),
        );

        $this->assertSame([900], $provider->filterIdentifiersInScope($this->request(), [900, 901, 1], 'id'));
    }

    #[Test]
    public function it_refuses_a_field_the_entity_does_not_map(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"reference" is not a mapped field');

        $this->provider()->filterIdentifiersInScope($this->request(), [1], 'reference');
    }

    private function provider(?callable $scope = null): DoctrineDataProvider
    {
        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->mapper(),
            configureBaseQueryBuilder: $scope,
        );
    }

    private function mapper(): RowMapperInterface
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
