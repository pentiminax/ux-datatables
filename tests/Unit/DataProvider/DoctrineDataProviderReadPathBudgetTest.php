<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Summary\Count;
use Pentiminax\UX\DataTables\Model\Summary\Sum;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountInvoice;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryNote;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryOrder;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryRegion;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The number of statements of a draw does not depend on the page length.
 *
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderReadPathBudgetTest extends TestCase
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

            public function log($level, $message, array $context = []): void
            {
                if (isset($context['sql'])) {
                    $this->statements[] = (string) $context['sql'];
                }
            }
        };

        $this->em = $this->createEntityManagerWithMiddlewares(
            [new LoggingMiddleware($logger)],
            CountInvoice::class,
            CountCustomer::class,
            CountTag::class,
            SummaryRegion::class,
            SummaryOrder::class,
            SummaryNote::class,
        );

        for ($i = 1; $i <= 30; ++$i) {
            $customer = new CountCustomer($i, "Customer $i");
            $region   = new SummaryRegion($i, "Region $i");
            $this->em->persist($customer);
            $this->em->persist($region);
            // Every third invoice has no region, to prove a null relation keeps its row.
            $this->em->persist(new CountInvoice($i, "INV-$i", $customer, 0 === $i % 3 ? null : $region));
        }

        $this->em->flush();
        $this->em->clear();
        $this->statements = [];
    }

    #[Test]
    public function the_statement_count_of_a_draw_does_not_grow_with_the_page_length(): void
    {
        $small = $this->statementsFor(length: 5);
        $large = $this->statementsFor(length: 30);

        $this->assertSame($small, $large);
        $this->assertSame(2, $large);
    }

    #[Test]
    public function a_rows_only_read_issues_a_single_statement_and_the_same_rows(): void
    {
        $summarized = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountInvoice::class,
            rowMapper: $this->mapper(),
            columns: [
                TextColumn::new('number'),
                TextColumn::new('customer')->field('customer.name'),
                TextColumn::new('region')->field('region.name'),
                TextColumn::new('id')->summarize(new Count()),
            ],
        );
        $request = new DataTableRequest(draw: null, columns: new Columns([]), start: 0, length: 0, rowsOnly: true);

        $rows = iterator_to_array($summarized->fetchData($request)->data);

        $this->assertCount(30, $rows);
        $this->assertSame(1, $this->statementsCount(), 'Only the page is read.');
        $this->assertSame(0, $this->countStatements());
    }

    #[Test]
    public function a_null_relation_keeps_its_row_and_maps_to_null(): void
    {
        $rows = iterator_to_array($this->provider()->fetchData($this->request(30))->data);

        $this->assertCount(30, $rows);
        $this->assertSame(['number' => 'INV-3', 'customer' => 'Customer 3', 'region' => null], $rows[2]);
        $this->assertSame(['number' => 'INV-1', 'customer' => 'Customer 1', 'region' => 'Region 1'], $rows[0]);
    }

    #[Test]
    public function a_join_with_a_restricting_condition_still_yields_the_real_related_value(): void
    {
        $restricted = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.customer', 'c', 'WITH', 'c.id > 20');

        $rows = iterator_to_array($this->provider($restricted)->fetchData($this->request(30))->data);

        $this->assertSame('Customer 1', $rows[0]['customer']);
    }

    #[Test]
    public function a_join_and_select_by_the_hook_is_reused_without_a_second_join(): void
    {
        $joined = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.customer', 'customer')
            ->addSelect('customer');

        $rows = iterator_to_array($this->provider($joined)->fetchData($this->request(30))->data);

        $this->assertSame('Customer 7', $rows[6]['customer']);
        $this->assertSame(3, $this->statementsCount());
    }

    #[Test]
    public function a_selected_inner_join_by_the_hook_is_reused_without_a_second_join(): void
    {
        $joined = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->innerJoin('e.customer', 'c', 'WITH', 'c.id > 20')
            ->addSelect('c');

        $rows = iterator_to_array($this->provider($joined)->fetchData($this->request(30))->data);

        $this->assertSame('Customer 21', $rows[0]['customer']);
        $this->assertSame(1, substr_count($this->pageStatement(), 'count_customer'));
    }

    #[Test]
    public function a_selected_conditioned_join_takes_precedence_over_a_plain_join(): void
    {
        $joined = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.customer', 'restricted', 'WITH', 'restricted.id > 20')
            ->addSelect('restricted')
            ->leftJoin('e.customer', 'ordering')
            ->orderBy('ordering.id');

        $rows = iterator_to_array($this->provider($joined)->fetchData($this->request(30))->data);

        $this->assertNull($rows[0]['customer']);
        $this->assertSame('Customer 21', $rows[20]['customer']);
    }

    #[Test]
    public function a_hidden_result_alias_does_not_collide_with_generated_join_aliases(): void
    {
        $query = static fn (QueryBuilder $qb): QueryBuilder => $qb->addSelect('e.id AS HIDDEN ux_fetch_1');

        $rows = iterator_to_array($this->provider($query)->fetchData($this->request(30))->data);

        $this->assertSame('Customer 1', $rows[0]['customer']);
    }

    #[Test]
    public function a_distinct_query_gets_no_fetch_join(): void
    {
        $distinct = static fn (QueryBuilder $qb): QueryBuilder => $qb->distinct();

        $rows = iterator_to_array($this->provider($distinct)->fetchData($this->request(30))->data);

        $this->assertSame(['number' => 'INV-1', 'customer' => 'Customer 1', 'region' => 'Region 1'], $rows[0]);
        $this->assertStringNotContainsString('JOIN', $this->pageStatement());
    }

    #[Test]
    public function ordering_by_a_relation_column_issues_a_single_count(): void
    {
        $ordered = static function (QueryBuilder $qb): QueryBuilder {
            $path = RelationFieldResolver::resolve($qb, 'e', 'customer.name');

            return $qb->addOrderBy($path, 'DESC');
        };

        $result = $this->provider($ordered)->fetchData($this->request(10));
        $rows   = iterator_to_array($result->data);

        $this->assertSame(30, $result->recordsTotal);
        $this->assertSame(30, $result->recordsFiltered);
        $this->assertSame('Customer 9', $rows[0]['customer']);
        $this->assertSame(1, $this->countStatements());
    }

    #[Test]
    public function a_search_on_a_relation_column_keeps_both_counts(): void
    {
        $search = static function (QueryBuilder $qb): QueryBuilder {
            $path = RelationFieldResolver::resolve($qb, 'e', 'customer.name');

            return $qb->andWhere("$path = :name")->setParameter('name', 'Customer 4');
        };

        $result = $this->provider($search)->fetchData($this->request(10));

        $this->assertSame(30, $result->recordsTotal);
        $this->assertSame(1, $result->recordsFiltered);
        $this->assertSame(2, $this->countStatements());
    }

    #[Test]
    public function a_to_many_search_with_a_fetch_joined_relation_and_summaries_keeps_rows_counts_and_totals(): void
    {
        $north = new SummaryRegion(101, 'North');
        $south = new SummaryRegion(102, 'South');
        $first = new SummaryOrder(1, 'paid', 1000, $north);
        $first->addNote('urgent');
        $first->addNote('urgent soon');
        $second = new SummaryOrder(2, 'paid', 2000, $north);
        $second->addNote('urgent');
        $second->addNote('plain');
        $third = new SummaryOrder(3, 'pending', 500, $south);
        $third->addNote('plain');

        foreach ([$first, $second, $third, new SummaryOrder(4, 'paid', null, $south), new SummaryOrder(5, 'pending', 700)] as $order) {
            $this->em->persist($order);
        }

        $this->em->flush();
        $this->em->clear();

        $urgent = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.notes', 'n')
            ->andWhere('n.text LIKE :text')
            ->setParameter('text', 'urgent%')
            ->leftJoin('e.region', 'region')
            ->addSelect('region');

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: SummaryOrder::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['id' => $row->id, 'region' => $row->region?->name];
                }
            },
            configureQueryBuilder: $urgent,
            columns: [NumberColumn::new('amount')->field('amount')->summarize(new Sum(), new Count())],
        );

        $result = $provider->fetchData($this->request(10));

        $this->assertSame([['id' => 1, 'region' => 'North'], ['id' => 2, 'region' => 'North']], array_values(iterator_to_array($result->data)));
        $this->assertSame(5, $result->recordsTotal);
        $this->assertSame(2, $result->recordsFiltered);
        $this->assertSame(['amount' => ['sum' => 3000, 'count' => 2]], $result->summaries);
    }

    #[Test]
    public function counting_without_a_to_many_join_uses_no_distinct(): void
    {
        $this->provider()->fetchData($this->request(10));

        $this->assertSame(0, $this->countStatements(distinct: true));
        $this->assertSame(1, $this->countStatements());
    }

    #[Test]
    public function counting_through_a_to_many_join_keeps_distinct(): void
    {
        $tagged = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.tags', 't')
            ->andWhere('t.label = :label')
            ->setParameter('label', 'x');

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->mapper(),
            configureQueryBuilder: $tagged,
        );
        $provider->fetchData($this->request(10));

        $this->assertSame(1, $this->countStatements(distinct: true));
        $this->assertSame(2, $this->countStatements());
    }

    #[Test]
    public function an_export_streams_the_same_rows(): void
    {
        $rows = iterator_to_array($this->provider()->iterateRows($this->request(10)), false);

        $this->assertCount(30, $rows);
        $this->assertSame(['number' => 'INV-30', 'customer' => 'Customer 30', 'region' => null], $rows[29]);
    }

    #[Test]
    public function a_column_hidden_by_a_static_permission_adds_no_join(): void
    {
        $symfony = $this->createStub(AuthorizationCheckerInterface::class);
        $symfony->method('isGranted')->willReturn(false);

        $runtime = (new DataTableRuntimeFactory(
            autoDataProviderFactory: new AutoDataProviderFactory($this->em),
            permissionChecker: new AuthorizationChecker($symfony),
        ))->createRuntime(
            table: (new DataTable('invoices'))->serverSide(),
            columns: [
                TextColumn::new('number'),
                TextColumn::new('customer')->field('customer.name')->permission('ROLE_HR'),
            ],
            asDataTable: new AsDataTable(entityClass: CountInvoice::class),
            baseMapper: static fn (CountInvoice $invoice): array => ['number' => $invoice->number],
            manualDataProviderFactory: static fn (): null => null,
            configureQueryBuilder: static fn ($qb) => $qb,
        );
        $runtime->handleRequest(new Request(query: ['draw' => 1]));

        $rows = json_decode((string) $runtime->getResponse()->getContent(), true)['data'];

        $this->assertSame(['number' => 'INV-1'], $rows[0]);
        $this->assertSame([], array_filter($this->statements, static fn (string $sql): bool => str_contains($sql, 'count_customer')));
    }

    /**
     * @param (callable(QueryBuilder):QueryBuilder)|null $configureQueryBuilder
     */
    private function provider(?callable $configureQueryBuilder = null): DoctrineDataProvider
    {
        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountInvoice::class,
            rowMapper: $this->mapper(),
            configureQueryBuilder: $configureQueryBuilder,
            columns: [
                TextColumn::new('number'),
                TextColumn::new('customer')->field('customer.name'),
                TextColumn::new('region')->field('region.name'),
            ],
        );
    }

    private function mapper(): RowMapperInterface
    {
        return new class implements RowMapperInterface {
            public function map(mixed $row): array
            {
                return $row instanceof CountInvoice
                    ? ['number' => $row->number, 'customer' => $row->customer?->name, 'region' => $row->region?->name]
                    : ['id' => $row->id];
            }
        };
    }

    private function request(int $length): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: 0, length: $length);
    }

    private function statementsFor(int $length): int
    {
        $this->em->clear();
        $this->statements = [];

        iterator_to_array($this->provider()->fetchData($this->request($length))->data);

        return $this->statementsCount();
    }

    private function statementsCount(): int
    {
        return \count($this->statements);
    }

    private function pageStatement(): string
    {
        $pages = array_values(array_filter(
            $this->statements,
            static fn (string $sql): bool => str_contains($sql, 'FROM count_invoice') && !str_contains($sql, 'COUNT('),
        ));

        $this->assertCount(1, $pages);

        return $pages[0];
    }

    private function countStatements(bool $distinct = false): int
    {
        return \count(array_filter(
            $this->statements,
            static fn (string $sql): bool => str_contains($sql, $distinct ? 'COUNT(DISTINCT' : 'COUNT('),
        ));
    }
}
