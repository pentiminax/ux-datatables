<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\ConvertedIdItem;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\ConvertedIdOwner;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\PrefixedId;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\PrefixedIdType;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * An identifier whose Doctrine type converts it (a Symfony Uuid, a prefixed key) is sent by the
 * browser as a string, hydrated as an object and stored as something else again.
 *
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderConvertedIdentifierTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        if (!Type::hasType(PrefixedIdType::NAME)) {
            Type::addType(PrefixedIdType::NAME, PrefixedIdType::class);
        }

        $this->em = $this->createEntityManager(ConvertedIdOwner::class, ConvertedIdItem::class);

        $itemId = 1;
        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $key) {
            $owner = new ConvertedIdOwner(new PrefixedId($key), 'owner '.$key);
            $owner->addItem(new ConvertedIdItem($itemId++));
            $owner->addItem(new ConvertedIdItem($itemId++));
            $this->em->persist($owner);
        }

        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_finds_the_ids_the_browser_sends_in_the_scope(): void
    {
        $inScope = $this->provider()->filterIdentifiersInScope($this->request(0, 10), ['a', 'c', 'zz'], 'id');

        $this->assertSame(['a', 'c'], $inScope);
    }

    #[Test]
    public function it_applies_the_scope_to_converted_ids(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name <> :hidden')
            ->setParameter('hidden', 'owner b'));

        $this->assertSame(['a'], $provider->filterIdentifiersInScope($this->request(0, 10), ['a', 'b'], 'id'));
    }

    #[Test]
    public function it_treats_an_id_the_type_refuses_as_out_of_scope(): void
    {
        $this->assertSame(['a'], $this->provider()->filterIdentifiersInScope($this->request(0, 10), ['A!', 'a', ''], 'id'));
    }

    #[Test]
    public function it_exports_converted_ids_when_a_collection_is_joined(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.items', 'i')
            ->orderBy('e.name', 'ASC'));

        $exported = array_column(
            iterator_to_array($provider->iterateRows($this->request(0, 10)), false),
            'id',
        );

        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f'], $exported);
    }

    #[Test]
    public function it_pages_converted_ids_in_order_when_a_collection_is_joined(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.items', 'i')
            ->orderBy('e.name', 'DESC'));

        $pages = [];
        foreach ([0, 2, 4] as $start) {
            $pages[] = array_column(iterator_to_array($provider->fetchData($this->request($start, 2))->data), 'id');
        }

        $this->assertSame([['f', 'e'], ['d', 'c'], ['b', 'a']], $pages);
    }

    private function provider(?callable $scope = null): DoctrineDataProvider
    {
        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: ConvertedIdOwner::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['id' => (string) $row->id];
                }
            },
            configureQueryBuilder: $scope,
            configureBaseQueryBuilder: $scope,
        );
    }

    private function request(int $start, int $length): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: $start, length: $length);
    }
}
