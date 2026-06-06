<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DataProviderResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CustomerListDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableProjectorTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__.'/../../Fixtures/Count'],
            isDevMode: true,
        );

        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em   = new EntityManager($connection, $config);

        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(CountCustomer::class),
            $this->em->getClassMetadata(CountTag::class),
        ]);

        $this->em->persist(new CountCustomer(1, 'Alpha'));
        $this->em->persist(new CountCustomer(2, 'Beta'));
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function projected_values_reach_the_response(): void
    {
        $table = new ProjectingCustomerTable();
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            runtimeFactory: new DataTableRuntimeFactory(
                dataProviderResolver: new DataProviderResolver(new AutoDataProviderFactory($this->em)),
            ),
        ));

        $table->handleRequest(new Request(query: ['draw' => 1, 'start' => 0, 'length' => 10]));

        $data = json_decode((string) $table->getResponse()->getContent(), true)['data'];

        $this->assertSame('BADGE:Alpha', $data[0]['badge']);
        $this->assertSame('BADGE:Beta', $data[1]['badge']);
    }
}

#[AsDataTable(entityClass: CountCustomer::class)]
final class ProjectingCustomerTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield NumberColumn::new('id');
        yield TextColumn::new('name');
        yield TextColumn::new('badge');
    }

    protected function projectPage(array $items): array
    {
        return array_map(
            static fn (CountCustomer $c): CustomerListDto => new CustomerListDto($c->id, $c->name, 'BADGE:'.$c->name),
            $items,
        );
    }
}
