<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderFilteredCountTest extends TestCase
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

        // Two customers; the first has several tags so a to-many join multiplies its rows.
        $alpha = new CountCustomer(1, 'Alpha');
        $alpha->addTag(new CountTag(10, 'red'));
        $alpha->addTag(new CountTag(11, 'green'));
        $alpha->addTag(new CountTag(12, 'blue'));

        $beta = new CountCustomer(2, 'Beta');
        $beta->addTag(new CountTag(20, 'red'));

        $this->em->persist($alpha);
        $this->em->persist($beta);
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_counts_distinct_root_entities_when_a_to_many_join_is_applied(): void
    {
        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['id' => $row->id];
                }
            },
            // Simulate a searchable path traversing a to-many association: joining tags
            // multiplies the customer rows (Alpha appears 3 times, Beta once → 4 joined rows).
            configureQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb->leftJoin('e.tags', 't'),
        );

        $result = $provider->fetchData($this->request());

        // Without DISTINCT the filtered count would be 4 (the inflated joined-row count).
        // The correct value is the number of distinct root customers: 2.
        $this->assertSame(2, $result->recordsFiltered);
        $this->assertSame(2, $result->recordsTotal);
    }

    private function request(): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: 0, length: 10);
    }
}
