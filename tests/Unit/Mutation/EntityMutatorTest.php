<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
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
    #[Test]
    public function it_deletes_flushes_and_publishes(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/server/entity-mutator-fixtures/{id}'], ['type' => 'delete', 'id' => 5]);

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $this->resolverReturning(['/server/entity-mutator-fixtures/{id}']),
        );

        $mutator->delete(EntityMutatorFixture::class, 5);
    }

    #[Test]
    public function it_publishes_only_the_server_resolved_topics_and_never_client_input(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/server/only'], ['type' => 'delete', 'id' => 5]);

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $this->resolverReturning(['/server/only']),
        );

        // The delete() signature no longer accepts client topics: the only
        // possible publish target is the server-resolved configuration.
        $mutator->delete(EntityMutatorFixture::class, 5);
    }

    #[Test]
    public function it_does_not_publish_when_no_mercure_resolver_is_available(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with([], ['type' => 'delete', 'id' => 5]);

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
        );

        $mutator->delete(EntityMutatorFixture::class, 5);
    }

    #[Test]
    public function it_publishes_the_datatables_own_mercure_topics_instead_of_the_bare_resolver_ones(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/datatable-instance/topic'], ['type' => 'delete', 'id' => 5]);

        // The bare entity-class resolver would produce a *different* topic;
        // it must never be consulted once the DataTable instance resolves.
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->expects($this->never())->method('resolveMercureConfig');

        $dataProviderSpy = $this->createMock(DataProviderInterface::class);
        $dataProviderSpy->expects($this->never())->method('fetchData');

        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://hub.example/.well-known/mercure');

        $dataTable = new EntityMutatorServerSideFixtureDataTable($hubUrlResolver, $dataProviderSpy);

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EntityMutatorServerSideFixtureDataTable::class)->willReturn(true);
        $dataTables->method('get')->with(EntityMutatorServerSideFixtureDataTable::class)->willReturn($dataTable);

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $resolver,
            dataTables: $dataTables,
        );

        $mutator->delete(EntityMutatorFixture::class, 5, EntityMutatorServerSideFixtureDataTable::class);
    }

    #[Test]
    public function it_falls_back_to_the_bare_resolver_when_the_datatable_class_entity_does_not_match(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/server/entity-mutator-fixtures/{id}'], ['type' => 'delete', 'id' => 5]);

        $resolver = $this->resolverReturning(['/server/entity-mutator-fixtures/{id}']);

        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://hub.example/.well-known/mercure');

        // Registered, but configured for a different entity class than the
        // one being mutated: the guard must reject it and fall through.
        $mismatchedDataTable = new EntityMutatorMismatchedFixtureDataTable($hubUrlResolver);

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EntityMutatorMismatchedFixtureDataTable::class)->willReturn(true);
        $dataTables->method('get')->with(EntityMutatorMismatchedFixtureDataTable::class)->willReturn($mismatchedDataTable);

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $resolver,
            dataTables: $dataTables,
        );

        $mutator->delete(EntityMutatorFixture::class, 5, EntityMutatorMismatchedFixtureDataTable::class);
    }

    #[Test]
    public function it_falls_back_to_the_bare_resolver_when_the_datatable_class_is_not_registered(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/server/entity-mutator-fixtures/{id}'], ['type' => 'delete', 'id' => 5]);

        $resolver = $this->resolverReturning(['/server/entity-mutator-fixtures/{id}']);

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EntityMutatorServerSideFixtureDataTable::class)->willReturn(false);
        $dataTables->expects($this->never())->method('get');

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $resolver,
            dataTables: $dataTables,
        );

        $mutator->delete(EntityMutatorFixture::class, 5, EntityMutatorServerSideFixtureDataTable::class);
    }

    #[Test]
    public function it_resolves_client_side_datatable_topics_without_hydrating_data(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/client-side/topic'], ['type' => 'delete', 'id' => 5]);

        // The bare resolver must never be consulted once the DataTable resolves.
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->expects($this->never())->method('resolveMercureConfig');

        // A client-side table would hydrate its rows through this provider at
        // render time. Resolving topics for the mutation must not touch it.
        $dataProviderSpy = $this->createMock(DataProviderInterface::class);
        $dataProviderSpy->expects($this->never())->method('fetchData');

        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://hub.example/.well-known/mercure');

        $dataTable = new EntityMutatorClientSideFixtureDataTable($hubUrlResolver, $dataProviderSpy);

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EntityMutatorClientSideFixtureDataTable::class)->willReturn(true);
        $dataTables->method('get')->with(EntityMutatorClientSideFixtureDataTable::class)->willReturn($dataTable);

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $resolver,
            dataTables: $dataTables,
        );

        $mutator->delete(EntityMutatorFixture::class, 5, EntityMutatorClientSideFixtureDataTable::class);
    }

    #[Test]
    public function it_falls_back_to_the_bare_resolver_when_the_datatable_mercure_hub_url_is_unresolvable(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/server/entity-mutator-fixtures/{id}'], ['type' => 'delete', 'id' => 5]);

        // The DataTable's own resolution throws (unresolvable hub URL). Because
        // this runs AFTER flush(), it must never bubble up and turn an
        // already-committed mutation into a 500 — it degrades to the bare resolver.
        $resolver = $this->resolverReturning(['/server/entity-mutator-fixtures/{id}']);

        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn(null);

        $dataTable = new EntityMutatorUnresolvableHubFixtureDataTable($hubUrlResolver);

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EntityMutatorUnresolvableHubFixtureDataTable::class)->willReturn(true);
        $dataTables->method('get')->with(EntityMutatorUnresolvableHubFixtureDataTable::class)->willReturn($dataTable);

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $resolver,
            dataTables: $dataTables,
        );

        $mutator->delete(EntityMutatorFixture::class, 5, EntityMutatorUnresolvableHubFixtureDataTable::class);
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
            ->with(['/server/entity-mutator-fixtures/{id}'], ['type' => 'edit', 'id' => 5, 'field' => 'enabled']);

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $accessor,
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $this->resolverReturning(['/server/entity-mutator-fixtures/{id}']),
        );

        $mutator->setProperty(EntityMutatorFixture::class, 5, 'enabled', true);
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

        $mutator = new EntityMutator(new EntityLocator($this->registry($manager)), $accessor, $publisher, new PermissionChecker());

        $this->expectException(PropertyNotWritableException::class);
        $mutator->setProperty(EntityMutatorFixture::class, 5, 'enabled', true);
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

        $mutator = new EntityMutator(new EntityLocator($this->registry($manager)), $accessor, $publisher, new PermissionChecker());

        $this->expectException(FieldNotToggleableException::class);
        $mutator->setProperty(EntityMutatorFixture::class, 5, 'admin', true);
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

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            $this->denyingChecker('DELETE', $entity),
        );

        $this->expectException(MutationNotAllowedException::class);

        try {
            $mutator->delete(EntityMutatorFixture::class, 5);
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

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $accessor,
            $publisher,
            $this->denyingChecker('EDIT', $entity),
        );

        $this->expectException(MutationNotAllowedException::class);

        try {
            $mutator->setProperty(EntityMutatorFixture::class, 5, 'enabled', true);
        } catch (MutationNotAllowedException $exception) {
            $this->assertSame(403, $exception->getStatusCode());

            throw $exception;
        }
    }

    #[Test]
    public function it_maps_a_flush_failure_to_a_persistence_exception_on_delete(): void
    {
        $entity = new EntityMutatorFixture();

        $dbalException = $this->dbalException();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush')->willThrowException($dbalException);

        // A failed persistence must never surface as a published mutation event.
        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $this->resolverReturning(['/server/entity-mutator-fixtures/{id}']),
        );

        try {
            $mutator->delete(EntityMutatorFixture::class, 5);
            $this->fail('Expected a MutationPersistenceException to be thrown.');
        } catch (MutationPersistenceException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('The operation could not be completed due to a data conflict.', $exception->getClientMessage());
            $this->assertSame($dbalException, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_maps_a_flush_failure_to_a_persistence_exception_on_set_property(): void
    {
        $entity = new EntityMutatorFixture();

        $dbalException = $this->dbalException();

        $manager = $this->managerReturning($entity, 5);
        $manager->expects($this->once())->method('flush')->willThrowException($dbalException);

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'enabled')->willReturn(true);
        $accessor->expects($this->once())->method('setValue')->with($entity, 'enabled', true);

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $accessor,
            $publisher,
            new PermissionChecker(),
            mercureConfigResolver: $this->resolverReturning(['/server/entity-mutator-fixtures/{id}']),
        );

        try {
            $mutator->setProperty(EntityMutatorFixture::class, 5, 'enabled', true);
            $this->fail('Expected a MutationPersistenceException to be thrown.');
        } catch (MutationPersistenceException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('The operation could not be completed due to a data conflict.', $exception->getClientMessage());
            $this->assertSame($dbalException, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_propagates_not_found_from_the_locator_on_delete(): void
    {
        $manager    = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn(null);
        $manager->method('getRepository')->willReturn($repository);
        $manager->expects($this->never())->method('flush');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = new EntityMutator(
            new EntityLocator($this->registry($manager)),
            $this->createMock(PropertyAccessorInterface::class),
            $publisher,
            new PermissionChecker(),
        );

        $this->expectException(EntityNotFoundException::class);
        $mutator->delete(EntityMutatorFixture::class, 404);
    }

    private function dbalException(): DBALException
    {
        return new class('constraint violation') extends \RuntimeException implements DBALException {};
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
            ->willReturn(new MercureConfig(topics: $topics, hubUrl: 'https://hub.example/.well-known/mercure'));

        return $resolver;
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

    protected function createDataProvider(): ?DataProviderInterface
    {
        return $this->dataProviderSpy;
    }
}

/**
 * A DataTable whose Mercure hub URL cannot be resolved: configureMercure()
 * throws a LogicException while resolving topics. The resolver must swallow it
 * and fall back to the bare entity-class resolver rather than bubbling up.
 */
#[AsDataTable(entityClass: EntityMutatorFixture::class, mercure: true)]
final class EntityMutatorUnresolvableHubFixtureDataTable extends AbstractDataTable
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
            ->mercure(topics: ['/datatable-instance/topic']);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
