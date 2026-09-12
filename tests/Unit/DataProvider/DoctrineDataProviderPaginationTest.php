<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderPaginationTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(CountCustomer::class, CountTag::class);

        foreach (range(1, 12) as $id) {
            $this->em->persist(new CountCustomer($id, 'Customer '.$id));
        }

        $this->em->flush();
        $this->em->clear();
    }

    /**
     * A negative offset made DBAL throw before the query even ran, turning a crafted
     * `start=-5` into an HTTP 500.
     */
    #[Test]
    public function it_ignores_a_negative_start_instead_of_failing(): void
    {
        $result = $this->provider()->fetchData(
            new DataTableRequest(draw: 1, columns: new Columns([]), start: -5, length: 3)
        );

        $this->assertSame([1, 2, 3], $this->ids($result->data));
    }

    /**
     * "Show all" on a table whose lengthMenu never offers it used to dump every row,
     * whatever the table size.
     */
    #[Test]
    public function it_caps_show_all_to_the_maximum_page_length_when_the_table_forbids_it(): void
    {
        $runtime = new DataTableRuntime(
            table: (new DataTable('customers'))->lengthMenu([10, 25]),
            dataProviderFactory: fn (): ?DataProviderInterface => $this->provider(),
            maxPageLength: 5,
        );

        $runtime->handleRequest(new Request(query: ['draw' => 1, 'start' => -5, 'length' => -1]));

        $payload = json_decode((string) $runtime->getResponse()->getContent(), true);

        $this->assertCount(5, $payload['data']);
        $this->assertSame([1, 2, 3, 4, 5], array_column($payload['data'], 'id'));
    }

    private function provider(): DoctrineDataProvider
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
        );
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
