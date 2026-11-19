<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\OptimisticLockException;
use Pentiminax\UX\DataTables\Ajax\AuthorizedRow;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Exception\FieldNotToggleableException;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Mutation\MutationContext;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;
use Pentiminax\UX\DataTables\Tests\Fixtures\Security\TestAuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Topic resolution itself is covered by MercureTopicResolverTest; here the
 * resolver is a stub and only the publish/flush/permission behavior matters.
 *
 * @internal
 */
#[CoversClass(EntityMutator::class)]
final class EntityMutatorTest extends TestCase
{
    use CollectsDeprecations;

    /**
     * The topics the injected topic resolver produces.
     */
    private const RESOLVED_TOPICS = ['/server/entity-mutator-fixtures/{id}'];

    private const DATA_TABLE_CLASS = 'App\\DataTable\\EntityMutatorFixtureDataTable';

    #[Test]
    public function it_deletes_flushes_and_publishes_on_the_resolved_topics(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning();
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush');

        // delete() accepts no client topics: the only possible publish target
        // is what the injected topic resolver returns for this table.
        $topicResolver = $this->createMock(MercureTopicResolver::class);
        $topicResolver->expects($this->once())
            ->method('resolveConfig')
            ->with(EntityMutatorFixture::class, self::DATA_TABLE_CLASS)
            ->willReturn(new MercureConfig(self::RESOLVED_TOPICS));

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(self::RESOLVED_TOPICS, ['type' => 'delete', 'id' => '5']);

        $this->mutator($manager, $publisher, topicResolver: $topicResolver)
            ->deleteRow($this->row($manager, $entity, 5));
    }

    #[Test]
    public function it_sets_a_property_flushes_and_publishes(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning();
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->once())->method('flush');

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'enabled')->willReturn(true);
        $accessor->expects($this->once())->method('setValue')->with($entity, 'enabled', true);

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(self::RESOLVED_TOPICS, ['type' => 'edit', 'id' => '5', 'field' => 'enabled']);

        $mutator = $this->mutator(
            $manager,
            $publisher,
            accessor: $accessor,
            topicResolver: $this->topicResolverReturning(self::RESOLVED_TOPICS),
        );

        $mutator->toggleRow($this->row($manager, $entity, 5, 'enabled'), true);
    }

    #[Test]
    public function it_throws_and_does_not_flush_when_the_property_is_not_writable(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning();
        $manager->expects($this->never())->method('flush');

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->method('isWritable')->with($entity, 'enabled')->willReturn(false);
        $accessor->expects($this->never())->method('setValue');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator($manager, $publisher, accessor: $accessor);

        $this->expectException(PropertyNotWritableException::class);
        $mutator->toggleRow($this->row($manager, $entity, 5, 'enabled'), true);
    }

    #[Test]
    public function it_throws_and_does_not_flush_when_the_field_is_not_a_mapped_boolean(): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning();
        $manager->expects($this->never())->method('flush');

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects($this->never())->method('setValue');

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator($manager, $publisher, accessor: $accessor);

        $this->expectException(FieldNotToggleableException::class);
        $mutator->toggleRow($this->row($manager, $entity, 5, 'admin'), true);
    }

    #[Test]
    #[DataProvider('flushFailures')]
    public function it_maps_a_flush_failure_to_a_persistence_exception_on_delete(\Throwable $failure): void
    {
        $entity = new EntityMutatorFixture();

        $manager = $this->managerReturning();
        $manager->expects($this->once())->method('remove')->with($entity);
        $manager->expects($this->once())->method('flush')->willThrowException($failure);

        // A failed persistence must never surface as a published mutation event.
        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $mutator = $this->mutator($manager, $publisher, topicResolver: $this->topicResolverReturning(self::RESOLVED_TOPICS));

        $this->assertMapsToPersistenceException(
            fn () => $mutator->deleteRow($this->row($manager, $entity, 5)),
            $failure,
        );
    }

    #[Test]
    public function it_maps_a_flush_failure_to_a_persistence_exception_on_set_property(): void
    {
        $entity = new EntityMutatorFixture();

        $dbalException = self::dbalException();

        $manager = $this->managerReturning();
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
            topicResolver: $this->topicResolverReturning(self::RESOLVED_TOPICS),
        );

        $this->assertMapsToPersistenceException(
            fn () => $mutator->toggleRow($this->row($manager, $entity, 5, 'enabled'), true),
            $dbalException,
        );
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
        ?AuthorizationChecker $permissionChecker = null,
        ?MercureTopicResolver $topicResolver = null,
    ): EntityMutator {
        return new EntityMutator(
            new EntityLocator(null),
            $accessor ?? $this->createStub(PropertyAccessorInterface::class),
            $publisher,
            $permissionChecker ?? new AuthorizationChecker(new TestAuthorizationChecker()),
            $topicResolver     ?? new MercureTopicResolver(),
            new MutationFlusher(),
        );
    }

    /**
     * @param string[] $topics
     */
    private function topicResolverReturning(array $topics): MercureTopicResolver
    {
        $topicResolver = $this->createStub(MercureTopicResolver::class);
        $topicResolver->method('resolveConfig')->willReturn(new MercureConfig($topics));

        return $topicResolver;
    }

    private function row(EntityManagerInterface $manager, object $entity, int $id, ?string $field = null): AuthorizedRow
    {
        return new AuthorizedRow(
            table: new ResolvedDataTable($this->createStub(AbstractDataTable::class), EntityMutatorFixture::class, self::DATA_TABLE_CLASS),
            action: null,
            context: new MutationContext($entity, $manager),
            id: $id,
            field: $field,
            entityClass: null === $field ? null : EntityMutatorFixture::class,
        );
    }

    private function managerReturning(): EntityManagerInterface
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getClassMetadata')->willReturn($this->booleanFieldMetadata('enabled'));

        return $manager;
    }

    private function booleanFieldMetadata(string $field): ClassMetadata
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('hasField')->willReturnCallback(static fn (string $name): bool => $name === $field);
        $metadata->method('getTypeOfField')->willReturnCallback(static fn (string $name): ?string => $name === $field ? 'boolean' : null);

        return $metadata;
    }
}

final class EntityMutatorFixture
{
}
