<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\FieldNotToggleableException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
        );

        $mutator->delete(EntityMutatorFixture::class, 5);
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

        $mutator = new EntityMutator(new EntityLocator($this->registry($manager)), $accessor, $publisher);

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

        $mutator = new EntityMutator(new EntityLocator($this->registry($manager)), $accessor, $publisher);

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
        );

        $this->expectException(EntityNotFoundException::class);
        $mutator->delete(EntityMutatorFixture::class, 404);
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
