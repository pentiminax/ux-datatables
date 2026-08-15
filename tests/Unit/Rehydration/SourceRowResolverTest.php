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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceRowResolver::class)]
final class SourceRowResolverTest extends TestCase
{
    /**
     * @param list<array<string, mixed>>                    $rows
     * @param list<int>                                     $expectedQueriedIds
     * @param list<SourceRowResolverUserFixture>            $foundEntities
     * @param array<int, SourceRowResolverUserFixture|null> $expected
     */
    #[Test]
    #[DataProvider('provideResolvableRows')]
    public function it_resolves_rows_through_a_single_batched_find_by(array $rows, array $expectedQueriedIds, array $foundEntities, array $expected): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['id' => $expectedQueriedIds])
            ->willReturn($foundEntities);

        $resolver = new SourceRowResolver(new RowIdentifierExtractor(), $this->createDoctrine($repository));

        $this->assertSame($expected, $resolver->resolve(SourceRowResolverUserFixture::class, $rows));
    }

    public static function provideResolvableRows(): iterable
    {
        $seven = new SourceRowResolverUserFixture(7);
        $nine  = new SourceRowResolverUserFixture(9);

        yield 'batches multiple rows into one query' => [
            [['id' => 7], ['id' => 9]],
            [7, 9],
            [$seven, $nine],
            [0 => $seven, 1 => $nine],
        ];

        yield 'aligns resolved entities with their original row keys' => [
            [['email' => 'no-id@example.com'], ['id' => 9]],
            [9],
            [$nine],
            [0 => null, 1 => $nine],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    #[Test]
    #[DataProvider('provideCasesWithoutDoctrineLookup')]
    public function it_returns_all_null_without_consulting_doctrine(?string $entityClass, array $rows): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->never())->method('getManagerForClass');

        $resolved = (new SourceRowResolver(new RowIdentifierExtractor(), $doctrine))->resolve($entityClass, $rows);

        $this->assertSame([0 => null], $resolved);
    }

    public static function provideCasesWithoutDoctrineLookup(): iterable
    {
        yield 'the entity class is null' => [null, [['id' => 7]]];

        yield 'no identifier can be resolved' => [SourceRowResolverUserFixture::class, [['email' => 'user@example.com']]];
    }

    #[Test]
    public function it_returns_all_null_when_doctrine_is_unavailable(): void
    {
        $resolved = (new SourceRowResolver(new RowIdentifierExtractor()))->resolve(SourceRowResolverUserFixture::class, [['id' => 7]]);

        $this->assertSame([0 => null], $resolved);
    }

    #[Test]
    public function it_skips_rehydration_for_composite_key_entities(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->never())->method('findBy');

        $resolved = (new SourceRowResolver(
            new RowIdentifierExtractor(),
            $this->createDoctrine($repository, ['user_id', 'role_id']),
        ))->resolve(SourceRowResolverUserFixture::class, [['id' => 7]]);

        $this->assertSame([0 => null], $resolved);
    }

    /**
     * @param list<string> $identifierFields
     */
    private function createDoctrine(ObjectRepository $repository, array $identifierFields = ['id']): ManagerRegistry
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn($identifierFields);
        $metadata->method('getIdentifierValues')->willReturnCallback(
            static fn (object $entity): array => ['id' => $entity->getId()],
        );

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getRepository')->with(SourceRowResolverUserFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->with(SourceRowResolverUserFixture::class)->willReturn($metadata);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->with(SourceRowResolverUserFixture::class)->willReturn($manager);

        return $doctrine;
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
