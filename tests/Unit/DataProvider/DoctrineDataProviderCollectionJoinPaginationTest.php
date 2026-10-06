<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\StringKeyItem;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\StringKeyOwner;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * LIMIT/OFFSET count SQL rows, so a page of a query joining a to-many association has to be cut
 * on distinct root identifiers instead.
 *
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderCollectionJoinPaginationTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    /** @var list<string> */
    private array $statements = [];

    protected function setUp(): void
    {
        $logger = new class($this->statements) extends AbstractLogger {
            /** @param list<string> $statements */
            public function __construct(private array &$statements)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (isset($context['sql'])) {
                    $this->statements[] = (string) $context['sql'];
                }
            }
        };

        $this->em = $this->createEntityManagerWithMiddlewares(
            [new LoggingMiddleware($logger)],
            CountCustomer::class,
            CountTag::class,
        );

        // Six customers, each with three "vip" tags; customer N also owns N extra "misc" tags
        // so the tag count differs per customer.
        $tagId = 1;
        foreach (range(1, 6) as $id) {
            $customer = new CountCustomer($id, 'Customer '.$id);
            foreach (range(1, 3) as $unused) {
                $customer->addTag(new CountTag($tagId++, 'vip'));
            }
            foreach (range(1, $id) as $unused) {
                $customer->addTag(new CountTag($tagId++, 'misc'));
            }
            $this->em->persist($customer);
        }

        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_returns_full_non_overlapping_pages_when_a_search_joins_a_collection(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.tags', 't')
            ->andWhere('t.label LIKE :label')
            ->setParameter('label', '%vip%')
            ->orderBy('e.id', 'ASC'));

        $pages = [];
        foreach ([0, 2, 4] as $start) {
            $result = $provider->fetchData($this->request($start, 2));

            $this->assertSame(6, $result->recordsFiltered);
            $pages[] = $this->ids($result->data);
        }

        $this->assertSame([[1, 2], [3, 4], [5, 6]], $pages);
    }

    #[Test]
    public function it_returns_an_empty_page_past_the_last_root(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.tags', 't')
            ->orderBy('e.id', 'ASC'));

        $this->assertSame([], $this->ids($provider->fetchData($this->request(6, 2))->data));
    }

    #[Test]
    public function it_orders_pages_by_a_hidden_computed_alias_combined_with_a_collection_join(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.tags', 't')
            ->addSelect('(SELECT COUNT(c.id) FROM '.CountTag::class.' c WHERE c.customer = e) AS HIDDEN tagCount')
            ->orderBy('tagCount', 'DESC'));

        $this->assertSame([6, 5], $this->ids($provider->fetchData($this->request(0, 2))->data));
        $this->assertSame([4, 3], $this->ids($provider->fetchData($this->request(2, 2))->data));
        $this->assertSame([2, 1], $this->ids($provider->fetchData($this->request(4, 2))->data));
    }

    #[Test]
    public function it_pages_distinct_roots_when_customize_query_builder_fetch_joins_a_collection(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->addSelect('t')
            ->leftJoin('e.tags', 't')
            ->orderBy('e.id', 'ASC'));

        $this->assertSame([1, 2], $this->ids($provider->fetchData($this->request(0, 2))->data));
        $this->assertSame([3, 4], $this->ids($provider->fetchData($this->request(2, 2))->data));
        $this->assertSame([5, 6], $this->ids($provider->fetchData($this->request(4, 2))->data));
    }

    #[Test]
    public function it_pages_distinct_roots_when_the_order_follows_a_collection_column(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.tags', 't')
            ->orderBy('t.label', 'ASC')
            ->addOrderBy('e.id', 'DESC'));

        $all = [];
        foreach ([0, 2, 4] as $start) {
            array_push($all, ...$this->ids($provider->fetchData($this->request($start, 2))->data));
        }

        $this->assertCount(6, array_unique($all));
    }

    #[Test]
    public function it_costs_one_extra_query_per_draw_when_a_collection_is_joined(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.tags', 't')
            ->orderBy('e.id', 'ASC'));

        $this->statements = [];
        iterator_to_array($provider->fetchData($this->request(0, 2))->data);

        // recordsTotal, recordsFiltered, identifiers, entities
        $this->assertCount(4, $this->selectStatements());
    }

    #[Test]
    public function it_keeps_a_single_page_query_when_only_a_single_valued_association_is_joined(): void
    {
        $this->em->clear();

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountTag::class,
            rowMapper: $this->idMapper(),
            configureQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->leftJoin('e.customer', 'c')
                ->andWhere('c.name LIKE :name')
                ->setParameter('name', 'Customer 1%')
                ->orderBy('e.id', 'ASC'),
        );

        $this->statements = [];
        $result           = $provider->fetchData($this->request(0, 2));

        $this->assertSame([1, 2], $this->ids($result->data));
        // recordsTotal, recordsFiltered, page
        $this->assertCount(3, $this->selectStatements());
    }

    #[Test]
    public function it_keeps_a_single_page_query_when_nothing_is_joined(): void
    {
        $provider = $this->provider(null);

        $this->statements = [];
        $result           = $provider->fetchData($this->request(2, 2));

        $this->assertSame([3, 4], $this->ids($result->data));
        // one shared COUNT (the base and full queries are identical), page
        $this->assertCount(2, $this->selectStatements());
    }

    #[Test]
    public function it_keeps_distinct_text_keys_that_compare_equal_as_numbers(): void
    {
        $em = $this->createEntityManager(StringKeyOwner::class, StringKeyItem::class);

        $itemId = 1;
        foreach (['1', '01', '001'] as $key) {
            $owner = new StringKeyOwner($key);
            $owner->addItem(new StringKeyItem($itemId++));
            $owner->addItem(new StringKeyItem($itemId++));
            $em->persist($owner);
        }

        $em->flush();
        $em->clear();

        $provider = new DoctrineDataProvider(
            em: $em,
            entityClass: StringKeyOwner::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['id' => $row->id];
                }
            },
            configureQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->leftJoin('e.items', 'i')
                ->orderBy('e.id', 'ASC'),
        );

        $pages = [];
        foreach ([0, 2] as $start) {
            $pages[] = $this->ids($provider->fetchData($this->request($start, 2))->data);
        }

        $this->assertSame([['001', '01'], ['1']], $pages);
    }

    private function provider(?callable $configureQueryBuilder): DoctrineDataProvider
    {
        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->idMapper(),
            configureQueryBuilder: $configureQueryBuilder,
        );
    }

    private function idMapper(): RowMapperInterface
    {
        return new class implements RowMapperInterface {
            public function map(mixed $row): array
            {
                return ['id' => $row->id];
            }
        };
    }

    private function request(int $start, int $length): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: $start, length: $length);
    }

    /**
     * @return list<string>
     */
    private function selectStatements(): array
    {
        return array_values(array_filter(
            $this->statements,
            static fn (string $sql): bool => str_starts_with(ltrim($sql), 'SELECT'),
        ));
    }

    /**
     * @param iterable<array<string, mixed>> $rows
     *
     * @return list<int>
     */
    private function ids(iterable $rows): array
    {
        return array_column(iterator_to_array($rows), 'id');
    }
}
