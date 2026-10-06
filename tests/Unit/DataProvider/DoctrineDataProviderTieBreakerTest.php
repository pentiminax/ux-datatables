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
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderTieBreakerTest extends TestCase
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

        foreach (range(1, 12) as $id) {
            $this->em->persist(new CountCustomer($id, 'Same name'));
        }

        $this->em->flush();
        $this->em->clear();
        $this->statements = [];
    }

    #[Test]
    public function it_pages_every_row_once_when_the_sort_column_has_ties(): void
    {
        $seen = [];
        foreach ([0, 5, 10] as $start) {
            $seen = [...$seen, ...$this->ids($this->provider($this->orderByName(...))->fetchData($this->request($start, 5))->data)];
        }

        $this->assertSame(range(1, 12), $seen);
    }

    #[Test]
    public function it_appends_the_identifier_after_the_requested_order(): void
    {
        iterator_to_array($this->provider($this->orderByName(...))->fetchData($this->request(0, 5))->data);

        $this->assertMatchesRegularExpression('/ORDER BY \w+\.name ASC, \w+\.id ASC LIMIT 5/', $this->pageStatement());
    }

    #[Test]
    public function it_does_not_order_twice_on_an_identifier_the_request_already_orders_on(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb->orderBy('e.id', 'DESC'));

        $result = $provider->fetchData($this->request(0, 3));

        $this->assertSame([12, 11, 10], $this->ids($result->data));
        $this->assertSame(1, substr_count($this->pageStatement(), 'ORDER BY'));
        $this->assertStringNotContainsString('ASC', $this->pageStatement());
    }

    #[Test]
    public function it_leaves_an_unordered_query_alone(): void
    {
        iterator_to_array($this->provider(null)->fetchData($this->request(0, 5))->data);

        $this->assertStringNotContainsString('ORDER BY', $this->pageStatement());
    }

    #[Test]
    public function it_leaves_a_grouped_query_alone(): void
    {
        $provider = $this->provider(static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->groupBy('e.id')
            ->orderBy('e.name', 'ASC'));

        iterator_to_array($provider->fetchData($this->request(0, 5))->data);

        $this->assertMatchesRegularExpression('/ORDER BY \w+\.name ASC LIMIT 5/', $this->pageStatement());
    }

    private function orderByName(QueryBuilder $qb): QueryBuilder
    {
        return $qb->orderBy('e.name', 'ASC');
    }

    private function provider(?\Closure $configureQueryBuilder): DoctrineDataProvider
    {
        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['id' => $row->id];
                }
            },
            configureQueryBuilder: $configureQueryBuilder,
        );
    }

    private function request(int $start, int $length): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: $start, length: $length);
    }

    private function pageStatement(): string
    {
        $pages = array_values(array_filter(
            $this->statements,
            static fn (string $sql): bool => !str_contains($sql, 'COUNT(')
        ));

        return end($pages) ?: '';
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
