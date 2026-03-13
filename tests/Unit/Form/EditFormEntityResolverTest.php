<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Form\EditFormEntityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EditFormEntityResolver::class)]
final class EditFormEntityResolverTest extends TestCase
{
    #[Test]
    public function it_returns_null_when_manager_is_missing(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormEntityResolverFixture::class)
            ->willReturn(null);

        $resolver = new EditFormEntityResolver($registry);

        $this->assertNull($resolver->resolve(EditFormEntityResolverFixture::class, 42));
    }

    #[Test]
    public function it_returns_null_when_entity_is_missing(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(EditFormEntityResolverFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->never())
            ->method('getClassMetadata');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormEntityResolverFixture::class)
            ->willReturn($entityManager);

        $resolver = new EditFormEntityResolver($registry);

        $this->assertNull($resolver->resolve(EditFormEntityResolverFixture::class, 42));
    }

    #[Test]
    public function it_returns_entity_context_when_entity_exists(): void
    {
        $entity = new EditFormEntityResolverFixture();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with('entity-id')
            ->willReturn($entity);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->willReturn(['id']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(EditFormEntityResolverFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(EditFormEntityResolverFixture::class)
            ->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormEntityResolverFixture::class)
            ->willReturn($entityManager);

        $resolver = new EditFormEntityResolver($registry);
        $context  = $resolver->resolve(EditFormEntityResolverFixture::class, 'entity-id');

        $this->assertNotNull($context);
        $this->assertSame($entity, $context->entity);
        $this->assertSame($entityManager, $context->manager);
        $this->assertSame(['id'], $context->identifierFields);
    }
}

final class EditFormEntityResolverFixture
{
}
