<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(MercureTopicResolver::class)]
final class MercureTopicResolverTest extends TestCase
{
    /**
     * The topics the bare entity-class resolver produces.
     */
    private const BARE_RESOLVER_TOPICS = ['/server/topic-resolver-fixtures/{id}'];

    private const HUB_URL = 'https://hub.example/.well-known/mercure';

    /**
     * A table class the locator never knows about, so topic resolution falls
     * back to the bare entity-class resolver.
     */
    private const UNREGISTERED_TABLE_CLASS = 'App\\DataTable\\UnregisteredDataTable';

    #[Test]
    public function it_falls_back_to_the_bare_entity_class_resolver_without_a_data_table(): void
    {
        $resolver = new MercureTopicResolver($this->resolverReturning(self::BARE_RESOLVER_TOPICS));

        $this->assertSame(
            self::BARE_RESOLVER_TOPICS,
            $resolver->resolve(TopicResolverFixture::class, self::UNREGISTERED_TABLE_CLASS),
        );
    }

    #[Test]
    public function it_resolves_no_topics_when_no_mercure_config_resolver_is_available(): void
    {
        $this->assertSame([], (new MercureTopicResolver())->resolve(TopicResolverFixture::class, self::UNREGISTERED_TABLE_CLASS));
    }

    #[Test]
    public function it_prefers_the_datatables_own_mercure_topics_over_the_bare_resolver_ones(): void
    {
        // The bare entity-class resolver would produce a *different* topic;
        // it must never be consulted once the DataTable instance resolves.
        $configResolver = $this->createMock(MercureConfigResolver::class);
        $configResolver->expects($this->never())->method('resolveMercureConfig');

        $dataProviderSpy = $this->createMock(DataProviderInterface::class);
        $dataProviderSpy->expects($this->never())->method('fetchData');

        $dataTable = new TopicResolverServerSideFixtureDataTable($this->hubUrlResolver(self::HUB_URL), $dataProviderSpy);

        $resolver = new MercureTopicResolver($configResolver, $this->dataTablesContaining($dataTable));

        $this->assertSame(
            ['/datatable-instance/topic'],
            $resolver->resolve(TopicResolverFixture::class, $dataTable::class),
        );
    }

    #[Test]
    public function it_resolves_client_side_datatable_topics_without_hydrating_data(): void
    {
        $configResolver = $this->createMock(MercureConfigResolver::class);
        $configResolver->expects($this->never())->method('resolveMercureConfig');

        // A client-side table would hydrate its rows through this provider at
        // render time. Resolving topics for the mutation must not touch it.
        $dataProviderSpy = $this->createMock(DataProviderInterface::class);
        $dataProviderSpy->expects($this->never())->method('fetchData');

        $dataTable = new TopicResolverClientSideFixtureDataTable($this->hubUrlResolver(self::HUB_URL), $dataProviderSpy);

        $resolver = new MercureTopicResolver($configResolver, $this->dataTablesContaining($dataTable));

        $this->assertSame(
            ['/client-side/topic'],
            $resolver->resolve(TopicResolverFixture::class, $dataTable::class),
        );
    }

    #[Test]
    public function it_falls_back_to_the_bare_resolver_when_the_datatable_class_entity_does_not_match(): void
    {
        // Registered, but configured for a different entity class than the
        // one being mutated: the guard must reject it and fall through.
        $dataTable = new TopicResolverMismatchedFixtureDataTable($this->hubUrlResolver(self::HUB_URL));

        $resolver = new MercureTopicResolver(
            $this->resolverReturning(self::BARE_RESOLVER_TOPICS),
            $this->dataTablesContaining($dataTable),
        );

        $this->assertSame(
            self::BARE_RESOLVER_TOPICS,
            $resolver->resolve(TopicResolverFixture::class, $dataTable::class),
        );
    }

    #[Test]
    public function it_falls_back_to_the_bare_resolver_when_the_datatable_class_is_not_registered(): void
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(TopicResolverServerSideFixtureDataTable::class)->willReturn(false);
        $dataTables->expects($this->never())->method('get');

        $resolver = new MercureTopicResolver($this->resolverReturning(self::BARE_RESOLVER_TOPICS), $dataTables);

        $this->assertSame(
            self::BARE_RESOLVER_TOPICS,
            $resolver->resolve(TopicResolverFixture::class, TopicResolverServerSideFixtureDataTable::class),
        );
    }

    #[Test]
    public function it_falls_back_to_the_bare_resolver_when_the_datatable_mercure_hub_url_is_unresolvable(): void
    {
        // The DataTable's own resolution throws (unresolvable hub URL). Because
        // this runs AFTER the mutation flushed, it must never bubble up and turn
        // an already-committed mutation into a 500.
        $dataTable = new TopicResolverServerSideFixtureDataTable($this->hubUrlResolver(null));

        $resolver = new MercureTopicResolver(
            $this->resolverReturning(self::BARE_RESOLVER_TOPICS),
            $this->dataTablesContaining($dataTable),
        );

        $this->assertSame(
            self::BARE_RESOLVER_TOPICS,
            $resolver->resolve(TopicResolverFixture::class, $dataTable::class),
        );
    }

    /**
     * @param string[] $topics
     */
    private function resolverReturning(array $topics): MercureConfigResolver
    {
        $resolver = $this->createMock(MercureConfigResolver::class);
        $resolver->method('resolveMercureConfig')
            ->with(TopicResolverFixture::class)
            ->willReturn(new MercureConfig(topics: $topics, hubUrl: self::HUB_URL));

        return $resolver;
    }

    private function hubUrlResolver(?string $hubUrl): MercureHubUrlResolver
    {
        $hubUrlResolver = $this->createMock(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn($hubUrl);

        return $hubUrlResolver;
    }

    private function dataTablesContaining(AbstractDataTable $dataTable): ContainerInterface
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with($dataTable::class)->willReturn(true);
        $dataTables->method('get')->with($dataTable::class)->willReturn($dataTable);

        return $dataTables;
    }
}

final class TopicResolverFixture
{
}

/**
 * A server-side DataTable with a manual Mercure configuration, mirroring
 * exactly what RenderingPreparer::configureMercure() would resolve at
 * render time. Server-side so that getDataTable() never triggers a data
 * fetch (AbstractDataTable::shouldHydrateClientSideData() short-circuits).
 *
 * Constructed with a hub URL resolver returning null, configureMercure()
 * throws a LogicException instead: the topic resolution must swallow it and
 * fall back to the bare entity-class resolver rather than bubbling up.
 */
#[AsDataTable(entityClass: TopicResolverFixture::class, mercure: true)]
final class TopicResolverServerSideFixtureDataTable extends AbstractDataTable
{
    public function __construct(
        private readonly ?MercureHubUrlResolver $mercureHubUrlResolver = null,
        private readonly ?DataProviderInterface $dataProviderSpy = null,
    ) {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(
                mercureHubUrlResolver: $this->mercureHubUrlResolver,
            )
        ));
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->serverSide()
            ->mercure(topics: ['/datatable-instance/topic']);
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
 * Registered under a class name that does not correspond to the entity being
 * mutated: the entity-class guard must reject it.
 */
#[AsDataTable(entityClass: \stdClass::class, mercure: true)]
final class TopicResolverMismatchedFixtureDataTable extends AbstractDataTable
{
    public function __construct(
        private readonly ?MercureHubUrlResolver $mercureHubUrlResolver = null,
    ) {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(
                mercureHubUrlResolver: $this->mercureHubUrlResolver,
            )
        ));
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->serverSide()
            ->mercure(topics: ['/mismatched/topic']);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}

/**
 * A client-side (NOT server-side) DataTable with a manual Mercure
 * configuration. Rendering it would hydrate rows through the data provider;
 * resolving topics for a mutation must NOT — the resolver skips hydration.
 */
#[AsDataTable(entityClass: TopicResolverFixture::class, mercure: true)]
final class TopicResolverClientSideFixtureDataTable extends AbstractDataTable
{
    public function __construct(
        private readonly ?MercureHubUrlResolver $mercureHubUrlResolver = null,
        private readonly ?DataProviderInterface $dataProviderSpy = null,
    ) {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(
                mercureHubUrlResolver: $this->mercureHubUrlResolver,
            )
        ));
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->mercure(topics: ['/client-side/topic']);
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
