<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\BinaryIdOwner;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\BinaryIdType;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Export reloads each chunk with `WHERE id IN (:ux_datatables_export_ids)`. Without the field's
 * array binding type, a BINARY primary key (uuid_binary and friends) binds as a string and the
 * IN clause matches nothing — the table still pages, but the CSV/Excel stream is empty.
 *
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderBinaryIdExportTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        if (!Type::hasType(BinaryIdType::NAME)) {
            Type::addType(BinaryIdType::NAME, BinaryIdType::class);
        }

        $this->em = $this->createEntityManager(BinaryIdOwner::class);

        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $this->em->persist(new BinaryIdOwner(random_bytes(16), $name));
        }

        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_exports_every_row_when_the_identifier_binds_as_binary(): void
    {
        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: BinaryIdOwner::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['name' => $row->name];
                }
            },
            configureQueryBuilder: static fn (QueryBuilder $qb): QueryBuilder => $qb->orderBy('e.name', 'ASC'),
            exportChunkSize: 1,
        );

        $request = new DataTableRequest(draw: 1, columns: new Columns([]), start: 0, length: 10);

        $this->assertSame(
            [['name' => 'Alpha'], ['name' => 'Beta'], ['name' => 'Gamma']],
            iterator_to_array($provider->fetchData($request)->data, false),
        );
        $this->assertSame(
            [['name' => 'Alpha'], ['name' => 'Beta'], ['name' => 'Gamma']],
            iterator_to_array($provider->iterateRows($request), false),
        );
    }
}
