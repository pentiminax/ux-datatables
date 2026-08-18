<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\FieldNotToggleableException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(EntityMutator::class)]
final class EntityMutatorTest extends TestCase
{
    /**
     * The topics the bare entity-class resolver produces.
     */
    private const BARE_RESOLVER_TOPICS = ['/server/entity-mutator-fixtures/{id}'];

    private const HUB_URL = 'https://hub.example/.well-known/mercure';

    /**
     * A table class the test locator never knows about, so topic resolution
     * falls back to the bare entity-class resolver.
     */
    private const UNREGISTERED_TABLE_CLASS = 'App\\DataTable\\UnregisteredDataTable';

    #[Test]
    public function it_deletes_flushes_and_publishes(): void
    {
        // The delete() signature does not accept client topics: the only
        // possible publish target is the server-resolved configuration.
        $this->assertDeletePublishesTopics(
            self::BARE_RESOLVER_TOPICS,
            resolver: $this->resolverReturning(self::BARE_RESOLVER_TOPICS),
        );
    }

    #[Test]
    public function it_does_not_publish_when_no_mercure_resolver_is_available(): void
    {
        $this->assertDeletePublishesTopics([]);
    }

    #[Test]
    public function it_publishes_the_datatables_own_mercure_topics_instead_of_the_bare_resolver_ones(): void
    {
        // The bare entity-class resolver would produce a *different* topic;
        // it must never be consulted once the DataTable instance resolves.
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->expects($this->never())->method('resolveMercureConfig');

        $dataProviderSpy = $this->createMock(DataProviderInterface::class);
        $dataProviderSpy->expects($this->never())->method('fetchData');

        $dataTable = new EntityMutatorServerSideFixtureDataTable($this->hubUrlResolver(self::HUB_URL), $dataProviderSpy);

        $this->assertDeletePublishesTopics(
            ['/datatable-instance/topic'],
            resolver: $resolver,
            dataTables: $this->dataTablesContaining($dataTable),
            dataTableClass: $dataTable::class,
        );
    }

    #[Test]
    public function it_resolves_client_side_datatable_topics_without_hydrating_data(): void
    {
        // The bare resolver must never be consulted once the DataTable resolves.
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->expects($this->never())->method('resolveMercureConfig');

        // A client-side table would hydrate its rows through this provider at
        // render time. Resolving topics for the mutation must not touch it.
        $dataProviderSpy = $this->createMock(DataProviderInterface::class);
        $dataProviderSpy->expects($this->never())->method('fetchData');

        $dataTable = new EntityMutatorClientSideFixtureDataTable($this->hubUrlResolver(self::HUB_URL), $dataProviderSpy);

        $this->assertDeletePublishesTopics(
            ['/client-side/topic'],
            resolver: $resolver,
            dataTables: $this->dataTablesContaining($dataTable),
            dataTableClass: $dataTable::class,
        );
    }

    #[Test]
    public function it_falls_back_to_the_bare_resolver_when_the_datatable_class_entity_does_not_match(): void
    {
        // Registered, but configured for a different entity class than the
        // one being mutated: the guard must reject it and fall through.
        $mismatchedDataTable = new EntityMutatorMismatchedFixtureDataTable($this->hubUrlResolver(self::HUB_URL));

        $this->assertDeletePublishesTopics(
            self::BARE_RESOLVER_TOPICS,
            resolver: $this->resolverReturning(self::BARE_RESOLVER_TOPICS),
            dataTables: $this->dataTablesContaining($mismatchedDataTable),
            dataTableClass: $mismatchedDataTable::class,
        );
    }

    #[Test]
    public function it_falls_back_to_the_bare_resolver_when_the_datatable_class_is_not_registered(): void
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EntityMutatorServerSideFixtureDataTable::class)->willReturn(false);
        $dataTables->expects($this->never())->method('get');

        $this->assertDeletePublishesTopics(
            self::BARE_RESOLVER_TOPICS,
            resolver: $this->resolverReturning(self::BARE_RESOLVER_TOPICS),
            dataTables: $dataTables,
            dataTableClass: EntityMutatorServerSideFixtureDataTable::class,
        );
    }

    #[Test]
    public function it_falls_back_to_the_bare_resolver_when_the_datatable_mercure_hub_url_is_unresolvable(): void
    {
        // The DataTable's own resolution throws (unresolvable hub URL). Because
        // this runs AFTER flush(), it must never bubble up and turn an
        // already-committed mutation into a 500 — it degrades to the bare resolver.
        $dataTable = new EntityMutatorServerSideFixtureDataTable($this->hubUrlResolver(null));

        $this->assertDeletePublishesTopics(
            self::BARE_RESOLVER_TOPICS,
            resolver: $this->resolverReturning(self::BARE_RESOLVER_TOPICS),
            dataTables: $this->dataTablesContaining($dataTable),
            dataTableClass: $dataTable::class,
        );
    }

    #[Test]
    public function it_sets_a_property_flushes_and_publishes(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->once())->method('flush');

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'enabled')->willReturn(true);
        $accessor->expects($this->once())->method('setValue')->with($entity, 'enabled', true);

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(self::BARE_RESOLVER_TOPICS, ['type' => 'edit', 'id' => 5, 'field' => 'enabled']);

        $mutator = $this->mutator(
            $manager,
            $publisher,
            accessor: $accessor,
            resolver: $this->resolverReturning(self::BARE_RESOLVER_TOPICS),
        );

        $mutator->setProperty(EntityMutatorFixture::class, 5, 'enabled', true, self::UNREGISTERED_TABLE_CLASS);
    }

    #[Test]
    public function it_throws_and_does_not_flush_when_the_property_is_not_writable(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->never())->method('flush');

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'enabled')->willReturn(false);
        $accessor->expects($this->never())->method('setValue');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator($manager, $publisher, accessor: $accessor);

        $this->expectException(PropertyNotWritableException::class);
        $mutator->setProperty(EntityMutatorFixture::class, 5, 'enabled', true, self::UNREGISTERED_TABLE_CLASS);
    }

    #[Test]
    public function it_throws_and_does_not_flush_when_the_field_is_not_a_mapped_boolean(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->never())->method('flush');

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects($this->never())->method('setValue');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator($manager, $publisher, accessor: $accessor);

        $this->expectException(FieldNotToggleableException::class);
        $mutator->setProperty(EntityMutatorFixture::class, 5, 'admin', true, self::UNREGISTERED_TABLE_CLASS);
    }

    #[Test]
    public function it_denies_deletion_and_does_not_remove_or_flush_when_not_granted(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->never())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator($manager, $publisher, permissionChecker: $this->denyingChecker('DELETE', $entity));

        $this->expectException(MutationNotAllowedException::class);

        try {
            $mutator->delete(EntityMutatorFixture::class, 5, self::UNREGISTERED_TABLE_CLASS);
        } catch (MutationNotAllowedException $exception) {
            $this->assertSame(403, $exception->getStatusCode());

            throw $exception;
        }
    }

    #[Test]
    public function it_denies_property_write_and_does_not_set_value_or_flush_when_not_granted(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->never())->method('flush');

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects($this->never())->method('isWritable');
        $accessor->expects($this->never())->method('setValue');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator(
            $manager,
            $publisher,
            accessor: $accessor,
            permissionChecker: $this->denyingChecker('EDIT', $entity),
        );

        $this->expectException(MutationNotAllowedException::class);

        try {
            $mutator->setProperty(EntityMutatorFixture::class, 5, 'enabled', true, self::UNREGISTERED_TABLE_CLASS);
        } catch (MutationNotAllowedException $exception) {
            $this->assertSame(403, $exception->getStatusCode());

            throw $exception;
        }
    }

    #[Test]
    #[DataProvider('flushFailures')]
    public function it_maps_a_flush_failure_to_a_persistence_exception_on_delete(\Throwable $failure): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush')->willThrowException($failure);

        // A failed persistence must never surface as a published mutation event.
        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator($manager, $publisher, resolver: $this->resolverReturning(self::BARE_RESOLVER_TOPICS));

        $this->assertMapsToPersistenceException(
            fn () => $mutator->delete(EntityMutatorFixture::class, 5, self::UNREGISTERED_TABLE_CLASS),
            $failure,
        );
    }

    #[Test]
    public function it_maps_a_flush_failure_to_a_persistence_exception_on_set_property(): void
    {
        $entity = new EntityMutatorFixture();

        $dbalException = self::dbalException();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('flush')->willThrowException($dbalException);

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'enabled')->willReturn(true);
        $accessor->expects($this->once())->method('setValue')->with($entity, 'enabled', true);

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator(
            $manager,
            $publisher,
            accessor: $accessor,
            resolver: $this->resolverReturning(self::BARE_RESOLVER_TOPICS),
        );

        $this->assertMapsToPersistenceException(
            fn () => $mutator->setProperty(EntityMutatorFixture::class, 5, 'enabled', true, self::UNREGISTERED_TABLE_CLASS),
            $dbalException,
        );
    }

    #[Test]
    public function it_propagates_not_found_from_the_locator_on_delete(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn(null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->expects($this->never())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator($manager, $publisher);

        $this->expectException(EntityNotFoundException::class);
        $mutator->delete(EntityMutatorFixture::class, 404, self::UNREGISTERED_TABLE_CLASS);
    }

    /**
     * Both failure families reach flush() and must be mapped identically.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function flushFailures(): iterable
    {
        yield 'DBAL unique constraint violation' => [self::dbalException()];

        // OptimisticLockException is an ORMException, NOT a DBAL\Exception: it is
        // the canonical 409 "data conflict" and must be mapped, not leaked as 500.
        yield 'ORM optimistic lock failure' => [OptimisticLockException::lockFailed(new EntityMutatorFixture())];
    }

    private static function dbalException(): DBALException
    {
        // A genuine Doctrine\DBAL\Exception subtype that exists as such in both
        // DBAL 3 and 4. It must never `implements Doctrine\DBAL\Exception`,
        // which is a class in DBAL 3 (fatal) and only an interface in DBAL 4.
        return new UniqueConstraintViolationException(self::driverException(), null);
    }

    private static function driverException(): DriverException
    {
        return new class('constraint violation') extends \RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return '23505';
            }
        };
    }

    /**
     * Deletes entity #5 and asserts it was removed, flushed once, and published
     * on exactly $expectedTopics.
     *
     * @param string[] $expectedTopics
     */
    private function assertDeletePublishesTopics(
        array $expectedTopics,
        ?MercureConfigResolverInterface $resolver = null,
        ?ContainerInterface $dataTables = null,
        string $dataTableClass = self::UNREGISTERED_TABLE_CLASS,
    ): void {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with($expectedTopics, ['type' => 'delete', 'id' => 5]);

        $mutator = $this->mutator($manager, $publisher, resolver: $resolver, dataTables: $dataTables);

        $mutator->delete(EntityMutatorFixture::class, 5, $dataTableClass);
    }

    /**
     * Asserts that running $act maps its underlying failure to a 409
     * MutationPersistenceException wrapping $expectedPrevious.
     */
    private function assertMapsToPersistenceException(callable $act, \Throwable $expectedPrevious): void
    {
        $caught = null;

        try {
            $act();
        } catch (MutationPersistenceException $exception) {
            $caught = $exception;
        }

        $this->assertInstanceOf(MutationPersistenceException::class, $caught);
        $this->assertSame(409, $caught->getStatusCode());
        $this->assertSame('The operation could not be completed due to a data conflict.', $caught->getClientMessage());
        $this->assertSame($expectedPrevious, $caught->getPrevious());
    }

    private function mutator(
        EntityManagerInterface $manager,
        MercurePublisherInterface $publisher,
        ?PropertyAccessorInterface $accessor = null,
        ?PermissionChecker $permissionChecker = null,
        ?MercureConfigResolverInterface $resolver = null,
        ?ContainerInterface $dataTables = null,
    ): EntityMutator {
        return new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $accessor ?? $this->createStub(PropertyAccessorInterface::class),
            $publisher,
            $permissionChecker ?? new PermissionChecker(),
            mercureConfigResolver: $resolver,
            dataTables: $dataTables,
        );
    }

    private function managerReturning(object $entity, int|string $id): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with($id)->willReturn($entity);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(EntityMutatorFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->willReturn($this->booleanFieldMetadata('enabled'));

        return $manager;
    }

    private function booleanFieldMetadata(string $field): ClassMetadata
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasField')->willReturnCallback(static fn (string $name): bool => $name === $field);
        $metadata->method('getTypeOfField')->willReturnCallback(static fn (string $name): ?string => $name === $field ? 'boolean' : null);

        return $metadata;
    }

    private function registry(EntityManagerInterface $manager): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(EntityMutatorFixture::class)->willReturn($manager);

        return $registry;
    }

    private function denyingChecker(string $attribute, object $subject): PermissionChecker
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with($attribute, $subject)->willReturn(false);

        return new PermissionChecker($checker);
    }

    /**
     * @param string[] $topics
     */
    private function resolverReturning(array $topics): MercureConfigResolverInterface
    {
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->method('resolveMercureConfig')
            ->with(EntityMutatorFixture::class)
            ->willReturn(new MercureConfig(topics: $topics, hubUrl: self::HUB_URL));

        return $resolver;
    }

    private function hubUrlResolver(?string $hubUrl): MercureHubUrlResolverInterface
    {
        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
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

final class EntityMutatorFixture
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
#[AsDataTable(entityClass: EntityMutatorFixture::class, mercure: true)]
final class EntityMutatorServerSideFixtureDataTable extends AbstractDataTable
{
    public function __construct(
        private readonly ?MercureHubUrlResolverInterface $mercureHubUrlResolver = null,
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
 * Registered under a class name that does not correspond to the entity
 * being mutated: EntityMutator must reject it via the entity-class guard.
 */
#[AsDataTable(entityClass: \stdClass::class, mercure: true)]
final class EntityMutatorMismatchedFixtureDataTable extends AbstractDataTable
{
    public function __construct(
        private readonly ?MercureHubUrlResolverInterface $mercureHubUrlResolver = null,
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
#[AsDataTable(entityClass: EntityMutatorFixture::class, mercure: true)]
final class EntityMutatorClientSideFixtureDataTable extends AbstractDataTable
{
    public function __construct(
        private readonly ?MercureHubUrlResolverInterface $mercureHubUrlResolver = null,
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
}
