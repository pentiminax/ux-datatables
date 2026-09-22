<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Attribute\DataTableColumn;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end cover for a table whose shape class is a DTO while Doctrine still queries the entity.
 *
 * @internal
 */
#[CoversNothing]
final class AbstractDataTableProjectedDataClassTest extends TestCase
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
    public function it_maps_the_projected_rows_and_orders_them_on_the_entity(): void
    {
        $result = $this->table()->fetchData($this->request(orderDir: 'desc'));

        $this->assertSame(2, $result->recordsTotal);
        $this->assertSame([
            ['id' => 2, 'name' => 'CUSTOMER:Beta'],
            ['id' => 1, 'name' => 'CUSTOMER:Alpha'],
        ], iterator_to_array($result->data));
    }

    #[Test]
    public function it_searches_the_entity_and_returns_the_projected_value(): void
    {
        $result = $this->table()->fetchData($this->request(search: 'Alpha'));

        $this->assertSame(1, $result->recordsFiltered);
        $this->assertSame([['id' => 1, 'name' => 'CUSTOMER:Alpha']], iterator_to_array($result->data));
    }

    private function table(): AbstractDataTable
    {
        $table = new ProjectedCustomersDataTable();
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            runtimeFactory: new DataTableRuntimeFactory(new AutoDataProviderFactory($this->em)),
        ));

        return $table;
    }

    private function request(string $orderDir = 'asc', ?string $search = null): DataTableRequest
    {
        $columns = new Columns([
            'id'   => new Column(data: 'id', name: 'id', searchable: false, orderable: true),
            'name' => new Column(data: 'name', name: 'name', searchable: true, orderable: true),
        ]);

        return new DataTableRequest(
            draw: 1,
            columns: $columns,
            start: 0,
            length: 10,
            search: null === $search ? null : new Search(value: $search, regex: false),
            order: [new Order(column: 1, dir: $orderDir, name: 'name')],
        );
    }
}

final readonly class ProjectedCustomerRow
{
    public function __construct(
        #[DataTableColumn]
        public int $id,
        #[DataTableColumn]
        public string $name,
    ) {
    }
}

#[AsDataTable(dataClass: ProjectedCustomerRow::class, entityClass: CountCustomer::class)]
final class ProjectedCustomersDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide(true);
    }

    protected function projectPage(array $items): ?array
    {
        return array_map(
            static fn (CountCustomer $customer): ProjectedCustomerRow => new ProjectedCustomerRow($customer->id, 'CUSTOMER:'.$customer->name),
            $items,
        );
    }
}
