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
final class DoctrineDataProviderSkippedCountTest extends TestCase
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

        foreach (['Alpha', 'Beta', 'Gamma'] as $index => $name) {
            $this->em->persist(new CountCustomer($index + 1, $name));
        }

        $this->em->flush();
        $this->em->clear();
        $this->statements = [];
    }

    #[Test]
    public function it_issues_a_single_count_when_no_criterion_narrows_the_query(): void
    {
        $result = $this->provider()->fetchData($this->request());

        $this->assertSame(3, $result->recordsTotal);
        $this->assertSame(3, $result->recordsFiltered);
        $this->assertSame(1, $this->countStatements());
    }

    #[Test]
    public function it_issues_a_single_count_when_a_scope_parameter_is_shared_by_both_queries(): void
    {
        $scope = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name != :excluded')
            ->setParameter('excluded', 'Beta');

        $result = $this->provider($scope, $scope)->fetchData($this->request());

        $this->assertSame(2, $result->recordsTotal);
        $this->assertSame(2, $result->recordsFiltered);
        $this->assertSame(1, $this->countStatements());
    }

    #[Test]
    public function it_keeps_both_counts_when_a_search_narrows_the_full_query(): void
    {
        $search = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name = :name')
            ->setParameter('name', 'Alpha');

        $result = $this->provider($search)->fetchData($this->request());

        $this->assertSame(3, $result->recordsTotal);
        $this->assertSame(1, $result->recordsFiltered);
        $this->assertSame(2, $this->countStatements());
    }

    #[Test]
    public function it_keeps_both_counts_when_only_a_parameter_value_differs(): void
    {
        $base = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name != :excluded')
            ->setParameter('excluded', 'Beta');
        $full = static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name != :excluded')
            ->setParameter('excluded', 'Alpha');

        $result = $this->provider($full, $base)->fetchData($this->request());

        $this->assertSame(2, $result->recordsTotal);
        $this->assertSame(2, $result->recordsFiltered);
        $this->assertSame(2, $this->countStatements());
    }

    #[Test]
    public function it_keeps_both_counts_when_numeric_strings_differ_only_loosely(): void
    {
        $bind = static fn (string $value): \Closure => static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.name = :name')
            ->setParameter('name', $value);

        $result = $this->provider($bind('1'), $bind('01'))->fetchData($this->request());

        $this->assertSame(2, $this->countStatements());
        $this->assertSame(0, $result->recordsFiltered);
    }

    #[Test]
    public function it_keeps_both_counts_when_only_the_full_grouped_query_is_limited(): void
    {
        $grouped = static fn (QueryBuilder $qb): QueryBuilder => $qb->groupBy('e.id');
        $limited = static fn (QueryBuilder $qb): QueryBuilder => $qb->groupBy('e.id')->setMaxResults(1);

        $result = $this->provider($limited, $grouped)->fetchData($this->request());

        $this->assertSame(3, $result->recordsTotal);
        $this->assertSame(1, $result->recordsFiltered);
    }

    /**
     * @param (callable(QueryBuilder):QueryBuilder)|null $configureQueryBuilder
     * @param (callable(QueryBuilder):QueryBuilder)|null $configureBaseQueryBuilder
     */
    private function provider(?callable $configureQueryBuilder = null, ?callable $configureBaseQueryBuilder = null): DoctrineDataProvider
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
            configureBaseQueryBuilder: $configureBaseQueryBuilder,
        );
    }

    private function request(): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: 0, length: 10);
    }

    private function countStatements(): int
    {
        return \count(array_filter(
            $this->statements,
            static fn (string $sql): bool => str_contains($sql, 'COUNT('),
        ));
    }
}
