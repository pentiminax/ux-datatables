<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CustomerListDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
#[CoversClass(RowContext::class)]
final class DoctrineDataProviderProjectorTest extends TestCase
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
    public function it_projects_the_page_and_pairs_source_with_item(): void
    {
        $captured = [];

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: new class($captured) implements RowMapperInterface {
                /** @param list<RowContext> $captured */
                public function __construct(private array &$captured)
                {
                }

                public function map(mixed $row): array
                {
                    $this->captured[] = $row;

                    return ['id' => $row->item->id, 'badge' => $row->item->badge];
                }
            },
            pageProjector: $this->projector(...),
        );

        $rows = iterator_to_array($provider->fetchData($this->request())->data);

        $this->assertSame([['id' => 1, 'badge' => 'BADGE:Alpha'], ['id' => 2, 'badge' => 'BADGE:Beta']], $rows);

        // The mapper received RowContext objects pairing the entity with its DTO.
        $this->assertContainsOnlyInstancesOf(RowContext::class, $captured);
        $this->assertInstanceOf(CountCustomer::class, $captured[0]->source);
        $this->assertInstanceOf(CustomerListDto::class, $captured[0]->item);
    }

    #[Test]
    public function it_throws_when_the_projector_changes_the_page_size(): void
    {
        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            pageProjector: static fn (array $items): array => [reset($items)],
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Page projector returned 1 items for a source page containing 2 items');

        iterator_to_array($provider->fetchData($this->request())->data);
    }

    #[Test]
    public function it_supports_an_empty_page(): void
    {
        $calledWith = null;

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: CountCustomer::class,
            rowMapper: $this->identityMapper(),
            configureQueryBuilder: static fn ($qb) => $qb->where('e.id = 999'),
            pageProjector: function (array $items) use (&$calledWith): array {
                $calledWith = $items;

                return $items;
            },
        );

        $rows = iterator_to_array($provider->fetchData($this->request())->data);

        $this->assertSame([], $rows);
        $this->assertSame([], $calledWith);
    }

    /**
     * @param list<CountCustomer> $items
     *
     * @return list<CustomerListDto>
     */
    private function projector(array $items): array
    {
        return array_map(
            static fn (CountCustomer $c): CustomerListDto => new CustomerListDto($c->id, $c->name, 'BADGE:'.$c->name),
            $items,
        );
    }

    private function identityMapper(): RowMapperInterface
    {
        return new class implements RowMapperInterface {
            public function map(mixed $row): array
            {
                return ['id' => $row instanceof RowContext ? $row->item->id : $row->id];
            }
        };
    }

    private function request(): DataTableRequest
    {
        return new DataTableRequest(draw: 1, columns: new Columns([]), start: 0, length: 10);
    }
}
