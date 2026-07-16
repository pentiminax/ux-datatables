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

        $resolved = $resolver->resolve(SourceRowResolverUserFixture::class, [
            ['id' => 7],
            ['id' => 9],
        ]);

        $this->assertSame([0 => $seven, 1 => $nine], $resolved);
    }

    #[Test]
    public function it_aligns_resolved_entities_with_their_original_row_keys(): void
    {
        $nine = new SourceRowResolverUserFixture(9);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findBy')->with(['id' => [9]])->willReturn([$nine]);

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

        $resolved = (new SourceRowResolver(new RowIdentifierExtractor(), $doctrine))->resolve(
            SourceRowResolverUserFixture::class,
            [
                ['email' => 'no-id@example.com'],
                ['id' => 9],
            ],
        );

        $this->assertSame([0 => null, 1 => $nine], $resolved);
    }

    #[Test]
    public function it_returns_all_null_when_the_entity_class_is_null(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->never())->method('getManagerForClass');

        $resolved = (new SourceRowResolver(new RowIdentifierExtractor(), $doctrine))->resolve(null, [['id' => 7]]);

        $this->assertSame([0 => null], $resolved);
    }

    #[Test]
    public function it_returns_all_null_when_doctrine_is_unavailable(): void
    {
        $resolved = (new SourceRowResolver(new RowIdentifierExtractor()))->resolve(SourceRowResolverUserFixture::class, [['id' => 7]]);

        $this->assertSame([0 => null], $resolved);
    }

    #[Test]
    public function it_returns_all_null_when_no_identifier_can_be_resolved(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->never())->method('getManagerForClass');

        $resolved = (new SourceRowResolver(new RowIdentifierExtractor(), $doctrine))->resolve(
            SourceRowResolverUserFixture::class,
            [['email' => 'user@example.com']],
        );

        $this->assertSame([0 => null], $resolved);
    }

    #[Test]
    public function it_skips_rehydration_for_composite_key_entities(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->never())->method('findBy');

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn(['user_id', 'role_id']);

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getClassMetadata')->with(SourceRowResolverUserFixture::class)->willReturn($metadata);
        $manager->method('getRepository')->with(SourceRowResolverUserFixture::class)->willReturn($repository);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->with(SourceRowResolverUserFixture::class)->willReturn($manager);

        $resolved = (new SourceRowResolver(new RowIdentifierExtractor(), $doctrine))->resolve(
            SourceRowResolverUserFixture::class,
            [['id' => 7]],
        );

        $this->assertSame([0 => null], $resolved);
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
