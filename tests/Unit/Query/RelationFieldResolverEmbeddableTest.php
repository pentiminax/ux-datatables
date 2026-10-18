<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountAddress;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\EmbeddedAddressOwner;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\NestedEmbeddableOwner;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Doctrine maps embeddable fields as dotted names on the owning class (`address.city`).
 * DQL uses that path with no JOIN; joining `e.address` makes Doctrine reject the query.
 *
 * @internal
 */
#[CoversClass(RelationFieldResolver::class)]
final class RelationFieldResolverEmbeddableTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(
            EmbeddedAddressOwner::class,
            NestedEmbeddableOwner::class,
        );

        $paris  = new EmbeddedAddressOwner(1, 'Alice', new CountAddress('Paris', 75001));
        $lyon   = new EmbeddedAddressOwner(2, 'Bob', new CountAddress('Lyon', 69001));
        $nantes = new EmbeddedAddressOwner(3, 'Cara', new CountAddress('Nantes', 44000));

        $this->em->persist($paris);
        $this->em->persist($lyon);
        $this->em->persist($nantes);
        $this->em->persist(new NestedEmbeddableOwner(10, 'Dana', $paris));
        $this->em->persist(new NestedEmbeddableOwner(11, 'Eve', $lyon));
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_resolves_an_embeddable_field_without_joining(): void
    {
        $qb = $this->em->createQueryBuilder()->select('e')->from(EmbeddedAddressOwner::class, 'e');

        $this->assertSame('e.address.city', RelationFieldResolver::resolve($qb, 'e', 'address.city'));
        $this->assertSame([], $qb->getDQLPart('join'));
        $this->assertTrue(RelationFieldResolver::supportsTextSearch($qb, 'address.city'));
        $this->assertSame('integer', RelationFieldResolver::resolveIntegerFieldType($qb, 'address.zip'));
        $this->assertFalse(RelationFieldResolver::supportsTextSearch($qb, 'address.zip'));
    }

    #[Test]
    public function it_resolves_an_embeddable_behind_an_association_without_joining_the_embeddable(): void
    {
        $qb = $this->em->createQueryBuilder()->select('e')->from(NestedEmbeddableOwner::class, 'e');

        $this->assertSame('partner.address.city', RelationFieldResolver::resolve($qb, 'e', 'partner.address.city'));
        $this->assertCount(1, $qb->getDQLPart('join')['e'] ?? []);
        $this->assertTrue(RelationFieldResolver::supportsTextSearch($qb, 'partner.address.city'));
    }

    #[Test]
    public function it_orders_and_searches_an_embeddable_column_through_the_query_pipeline(): void
    {
        $pipeline = new QueryFilterPipeline(new DefaultDataTableQueryIntentFactory());
        $columns  = [TextColumn::new('city', 'City')->setField('address.city')];
        $qb       = $this->em->createQueryBuilder()->select('e')->from(EmbeddedAddressOwner::class, 'e');

        $pipeline->apply(
            qb: $qb,
            request: new DataTableRequest(
                draw: 1,
                columns: new Columns(['city' => new Column('city', 'city', true, true)]),
                order: [new Order(0, 'asc', 'city')],
                search: new Search('yon', false),
            ),
            columns: $columns,
            filters: null,
            registry: new DefaultSearchStrategyRegistry(),
        );

        $ids = array_map(
            static fn (EmbeddedAddressOwner $owner): int => $owner->id,
            $qb->getQuery()->getResult(),
        );

        $this->assertSame([2], $ids);
    }

    #[Test]
    public function it_pages_an_embeddable_order_through_the_doctrine_provider(): void
    {
        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: EmbeddedAddressOwner::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['id' => $row->id, 'city' => $row->address->city];
                }
            },
            configureQueryBuilder: function ($qb, $request) {
                return (new QueryFilterPipeline(new DefaultDataTableQueryIntentFactory()))->apply(
                    qb: $qb,
                    request: $request,
                    columns: [TextColumn::new('city', 'City')->setField('address.city')],
                    filters: null,
                    registry: new DefaultSearchStrategyRegistry(),
                );
            },
        );

        $request = new DataTableRequest(
            draw: 1,
            columns: new Columns(['city' => new Column('city', 'city', true, true)]),
            start: 0,
            length: 2,
            order: [new Order(0, 'asc', 'city')],
        );

        $result = $provider->fetchData($request);
        $rows   = iterator_to_array($result->data);

        $this->assertSame(3, $result->recordsFiltered);
        $this->assertSame(['Lyon', 'Nantes'], array_column($rows, 'city'));
    }

    #[Test]
    public function it_reuses_an_association_join_already_on_the_query_builder(): void
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(NestedEmbeddableOwner::class, 'e')
            ->leftJoin('e.partner', 'p');

        $this->assertSame('p.address.city', RelationFieldResolver::resolve($qb, 'e', 'partner.address.city'));
        $this->assertCount(1, $qb->getDQLPart('join')['e']);
    }

    #[Test]
    public function it_reports_no_type_for_an_unmapped_embeddable_property(): void
    {
        $qb = $this->em->createQueryBuilder()->select('e')->from(EmbeddedAddressOwner::class, 'e');

        $this->assertNull(RelationFieldResolver::resolveIntegerFieldType($qb, 'address.missing'));
    }
}
