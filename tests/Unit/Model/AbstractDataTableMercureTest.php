<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use Pentiminax\UX\DataTables\Mercure\MercureTableConfigResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableMercureTest extends TestCase
{
    use BuildsRuntime;

    #[Test]
    public function it_returns_null_for_a_client_side_table_without_hydrating(): void
    {
        // A client-side table (no server-side, no ajax, no inline data) would
        // hydrate its rows at render time, which suppresses attribute/auto
        // Mercure updates. Resolving topics for a mutation must mirror that
        // suppression — returning null — WITHOUT touching the data provider.
        $dataProviderSpy = $this->createMock(DataProviderInterface::class);
        $dataProviderSpy->expects($this->never())->method($this->anything());

        $dataTable = new AbstractDataTableMercureClientSideFixture($dataProviderSpy);

        $this->assertNull(self::buildRuntime()->mercureConfig($dataTable));
    }

    #[Test]
    public function it_delegates_to_the_pure_resolver_for_a_server_side_table(): void
    {
        $hubUrlResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://hub.example/.well-known/mercure');

        $runtime = self::buildRuntime(mercure: new MercureTableConfigResolver(hubUrlResolver: $hubUrlResolver));

        $config = $runtime->mercureConfig(new AbstractDataTableMercureServerSideFixture());

        $this->assertNotNull($config);
        $this->assertSame(['/server-side/topic'], $config->topics);
    }
}

/**
 * A client-side (NOT server-side) DataTable whose Mercure updates the render
 * path would suppress once its rows are hydrated inline.
 */
#[AsDataTable(entityClass: \stdClass::class, mercure: true)]
final class AbstractDataTableMercureClientSideFixture extends AbstractDataTable
{
    public function __construct(
        private readonly ?DataProviderInterface $dataProviderSpy = null,
    ) {
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return $this->dataProviderSpy;
    }
}

/**
 * A server-side DataTable with a manual Mercure configuration; resolving its
 * topics delegates to the pure RenderingPreparer resolver.
 */
#[AsDataTable(entityClass: \stdClass::class, mercure: true)]
final class AbstractDataTableMercureServerSideFixture extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->serverSide()
            ->mercure(topics: ['/server-side/topic']);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
