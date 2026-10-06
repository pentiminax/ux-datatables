<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EntityLocator::class)]
final class EntityLocatorTest extends TestCase
{
    #[Test]
    public function it_returns_a_context_with_the_entity_and_its_manager(): void
    {
        $entity  = new EntityLocatorFixture();
        $manager = $this->managerFinding(7, $entity);

        $context = (new EntityLocator($this->registryFor($manager)))->locate(EntityLocatorFixture::class, 7);

        $this->assertSame($entity, $context->entity);
        $this->assertSame($manager, $context->manager);
    }

    #[Test]
    public function it_throws_when_the_entity_is_not_found(): void
    {
        $registry = $this->registryFor($this->managerFinding(404, null));

        $this->expectException(EntityNotFoundException::class);
        (new EntityLocator($registry))->locate(EntityLocatorFixture::class, 404);
    }

    #[Test]
    public function it_throws_when_no_manager_exists_for_the_class(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        (new EntityLocator($registry))->locate(EntityLocatorFixture::class, 1);
    }

    #[Test]
    public function it_throws_when_doctrine_is_unavailable(): void
    {
        $this->expectException(EntityNotFoundException::class);
        (new EntityLocator(null))->locate(EntityLocatorFixture::class, 1);
    }

    #[Test]
    public function it_throws_on_an_empty_entity_class_without_touching_doctrine(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $this->expectException(EntityNotFoundException::class);
        (new EntityLocator($registry))->locate('', 1);
    }

    #[Test]
    public function it_throws_on_a_null_id_without_touching_doctrine(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $this->expectException(EntityNotFoundException::class);
        (new EntityLocator($registry))->locate(EntityLocatorFixture::class, null);
    }

    #[Test]
    public function it_looks_up_by_a_mapped_non_identifier_field(): void
    {
        $entity     = new EntityLocatorFixture();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('find');
        $repository->expects($this->once())->method('findOneBy')->with(['sku' => '100'])->willReturn($entity);

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $metadata->method('hasField')->willReturnCallback(static fn (string $field): bool => 'sku' === $field);
        $metadata->method('hasAssociation')->willReturn(false);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(EntityLocatorFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->with(EntityLocatorFixture::class)->willReturn($metadata);

        $context = (new EntityLocator($this->registryFor($manager)))->locate(EntityLocatorFixture::class, '100', 'sku');

        $this->assertSame($entity, $context->entity);
    }

    #[Test]
    public function it_uses_find_when_the_field_is_the_single_identifier(): void
    {
        $entity     = new EntityLocatorFixture();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('find')->with('42')->willReturn($entity);
        $repository->expects($this->never())->method('findOneBy');

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(EntityLocatorFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->with(EntityLocatorFixture::class)->willReturn($metadata);

        $context = (new EntityLocator($this->registryFor($manager)))->locate(EntityLocatorFixture::class, '42', 'id');

        $this->assertSame($entity, $context->entity);
    }

    #[Test]
    public function it_falls_back_to_find_for_an_unmapped_id_field_name(): void
    {
        $entity     = new EntityLocatorFixture();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('find')->with('uuid-1')->willReturn($entity);
        $repository->expects($this->never())->method('findOneBy');

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn(['uuid']);
        $metadata->method('hasField')->willReturn(false);
        $metadata->method('hasAssociation')->willReturn(false);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(EntityLocatorFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->with(EntityLocatorFixture::class)->willReturn($metadata);

        $context = (new EntityLocator($this->registryFor($manager)))->locate(EntityLocatorFixture::class, 'uuid-1', 'id');

        $this->assertSame($entity, $context->entity);
    }

    private function managerFinding(int|string $id, ?object $entity): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with($id)->willReturn($entity);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(EntityLocatorFixture::class)->willReturn($repository);

        return $manager;
    }

    private function registryFor(EntityManagerInterface $manager): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(EntityLocatorFixture::class)->willReturn($manager);

        return $registry;
    }
}

final class EntityLocatorFixture
{
}
