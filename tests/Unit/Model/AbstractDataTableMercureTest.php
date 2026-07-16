<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableMercureTest extends TestCase
{
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

        $this->assertNull($dataTable->resolveMercureConfigWithoutHydration());
    }

    #[Test]
    public function it_delegates_to_the_pure_resolver_for_a_server_side_table(): void
    {
        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://hub.example/.well-known/mercure');

        $dataTable = new AbstractDataTableMercureServerSideFixture($hubUrlResolver);

        $config = $dataTable->resolveMercureConfigWithoutHydration();

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
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(),
        ));
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
    public function __construct(
        private readonly ?MercureHubUrlResolverInterface $mercureHubUrlResolver = null,
    ) {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(
                mercureHubUrlResolver: $this->mercureHubUrlResolver,
            ),
        ));
    }

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
