<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Builder;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryFilterPipeline::class)]
final class QueryFilterPipelineMultiOrderTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class);

        foreach ([[1, 'Beta'], [2, 'Alpha'], [3, 'Beta'], [4, 'Alpha']] as [$id, $name]) {
            $this->em->persist(new CountCustomer($id, $name));
        }

        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_orders_the_query_by_every_requested_column(): void
    {
        $request = $this->request([new Order(0, 'asc', 'name'), new Order(1, 'desc', 'id')]);

        $this->assertSame([4, 2, 3, 1], $this->orderedIds($request));
    }

    #[Test]
    public function it_honors_the_request_order_of_the_criteria(): void
    {
        $request = $this->request([new Order(1, 'desc', 'id'), new Order(0, 'asc', 'name')]);

        $this->assertSame([4, 3, 2, 1], $this->orderedIds($request));
    }

    /**
     * @param list<Order> $order
     */
    private function request(array $order): DataTableRequest
    {
        return new DataTableRequest(
            draw: 1,
            columns: new Columns([
                'name' => new Column('name', 'name', true, true),
                'id'   => new Column('id', 'id', true, true),
            ]),
            order: $order,
        );
    }

    /**
     * @return list<int>
     */
    private function orderedIds(DataTableRequest $request): array
    {
        $qb = $this->em->createQueryBuilder()->select('e')->from(CountCustomer::class, 'e');

        (new QueryFilterPipeline(new DefaultDataTableQueryIntentFactory()))->apply(
            qb: $qb,
            request: $request,
            columns: [TextColumn::new('name', 'Name')->setField('name'), NumberColumn::new('id', 'Id')->setField('id')],
            filters: null,
            registry: new DefaultSearchStrategyRegistry(),
        );

        return array_map(static fn (CountCustomer $customer): int => $customer->id, $qb->getQuery()->getResult());
    }
}
