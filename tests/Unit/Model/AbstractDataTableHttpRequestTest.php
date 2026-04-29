<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableHttpRequestTest extends TestCase
{
    #[Test]
    public function test_give_n_no_handled_request_whe_n_get_http_request_is_accessed_the_n_null_is_returned(): void
    {
        $table = new HttpRequestAwareTable();

        $this->assertNull($table->exposedHttpRequest());
    }

    #[Test]
    public function test_give_n_handled_request_whe_n_get_http_request_is_accessed_the_n_same_request_is_returned(): void
    {
        $table   = new HttpRequestAwareTable();
        $request = new Request(query: ['draw' => 3, 'genre' => 'sci-fi']);

        $table->handleRequest($request);

        $this->assertSame($request, $table->exposedHttpRequest());
    }

    #[Test]
    public function test_give_n_custom_http_filter_whe_n_configure_query_builder_runs_the_n_it_reads_the_current_http_request(): void
    {
        $table = new HttpRequestAwareTable();
        $table->handleRequest(new Request(query: ['draw' => 3, 'genre' => 'sci-fi']));

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.genre = :genre')
            ->willReturn($qb);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('genre', 'sci-fi')
            ->willReturn($qb);
        $qb->expects($this->once())
            ->method('addOrderBy')
            ->with('e.id', 'desc')
            ->willReturn($qb);

        $request = new DataTableRequest(
            draw: 3,
            columns: new Columns([]),
            order: [new Order(column: 0, dir: 'desc', name: 'id')],
        );

        $result = $table->exposedConfigureQueryBuilder($qb, $request);

        $this->assertSame($qb, $result);
    }
}

final class HttpRequestAwareTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function exposedHttpRequest(): ?Request
    {
        return $this->getHttpRequest();
    }

    public function exposedConfigureQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        return $this->configureQueryBuilder($qb, $request);
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $genre = $this->getHttpRequest()?->query->get('genre');
        if (null === $genre) {
            return $qb;
        }

        return $qb
            ->andWhere('e.genre = :genre')
            ->setParameter('genre', $genre);
    }
}
