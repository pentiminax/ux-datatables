<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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
        $registry = $this->createMock(ManagerRegistry::class);
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
