<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Rehydration;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Pentiminax\UX\DataTables\Rehydration\RowIdentifierExtractor;
use Pentiminax\UX\DataTables\Rehydration\SourceRowResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceRowResolver::class)]
final class SourceRowResolverTest extends TestCase
{
    #[Test]
    public function it_batches_multiple_rows_into_a_single_find_by(): void
    {
        $seven = new SourceRowResolverUserFixture(7);
        $nine  = new SourceRowResolverUserFixture(9);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['id' => [7, 9]])
            ->willReturn([$seven, $nine]);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $metadata->method('getIdentifierValues')->willReturnCallback(
            static fn (object $entity): array => ['id' => $entity->getId()],
        );

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getRepository')->with(SourceRowResolverUserFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->with(SourceRowResolverUserFixture::class)->willReturn($metadata);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->with(SourceRowResolverUserFixture::class)->willReturn($manager);

        $resolver = new SourceRowResolver(new RowIdentifierExtractor(), $doctrine);

        $map = $resolver->resolve(SourceRowResolverUserFixture::class, [
            ['id' => 7],
            ['id' => 9],
        ]);

        $this->assertSame($seven, $map->sourceFor(['id' => 7]));
        $this->assertSame($nine, $map->sourceFor(['id' => 9]));
    }

    #[Test]
    public function it_returns_an_empty_map_when_the_entity_class_is_null(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->never())->method('getManagerForClass');

        $map = (new SourceRowResolver(new RowIdentifierExtractor(), $doctrine))->resolve(null, [['id' => 7]]);

        $this->assertNull($map->sourceFor(['id' => 7]));
    }

    #[Test]
    public function it_returns_an_empty_map_when_doctrine_is_unavailable(): void
    {
        $map = (new SourceRowResolver(new RowIdentifierExtractor()))->resolve(SourceRowResolverUserFixture::class, [['id' => 7]]);

        $this->assertNull($map->sourceFor(['id' => 7]));
    }

    #[Test]
    public function it_returns_an_empty_map_when_no_identifier_can_be_resolved(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->never())->method('getManagerForClass');

        $map = (new SourceRowResolver(new RowIdentifierExtractor(), $doctrine))->resolve(
            SourceRowResolverUserFixture::class,
            [['email' => 'user@example.com']],
        );

        $this->assertNull($map->sourceFor(['email' => 'user@example.com']));
    }
}

final class SourceRowResolverUserFixture
{
    public function __construct(private readonly int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
