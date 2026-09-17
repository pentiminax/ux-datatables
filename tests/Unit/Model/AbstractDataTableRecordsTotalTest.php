<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * End-to-end regression test for the recordsTotal / customizeQueryBuilder() gap: proves the
 * wiring all the way from AbstractDataTable::runtime() through DataTableRuntimeFactory
 * and AutoDataProviderFactory into DoctrineDataProvider, not just the
 * DoctrineDataProvider unit itself. A business-rule override that excludes a row must be
 * reflected in recordsTotal in the real JSON response, the same way it already was in
 * recordsFiltered and the returned page.
 *
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableRecordsTotalTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class);

        $this->em->persist(new CountCustomer(1, 'Alpha'));
        $this->em->persist(new CountCustomer(2, 'Beta'));
        $this->em->persist(new CountCustomer(3, 'Gamma'));
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function records_total_reflects_a_customize_query_builder_business_rule(): void
    {
        $table = new ExcludeBetaCustomerTable();
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            runtimeFactory: new DataTableRuntimeFactory(
                autoDataProviderFactory: new AutoDataProviderFactory($this->em),
            ),
        ));

        $table->handleRequest(new Request(query: ['draw' => 1, 'start' => 0, 'length' => 10]));

        $payload = json_decode((string) $table->getResponse()->getContent(), true);

        $this->assertSame(2, $payload['recordsTotal']);
        $this->assertSame(2, $payload['recordsFiltered']);
        $this->assertCount(2, $payload['data']);
    }
}

#[AsDataTable(entityClass: CountCustomer::class)]
final class ExcludeBetaCustomerTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield NumberColumn::new('id');
        yield TextColumn::new('name');
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        return $qb->andWhere("e.name != 'Beta'");
    }
}
