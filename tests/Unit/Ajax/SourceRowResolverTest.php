<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Ajax;

use Pentiminax\UX\DataTables\Ajax\SourceRowResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformItemResolver;
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
    public function it_returns_all_null_without_an_item_resolver(): void
    {
        $resolved = (new SourceRowResolver())->resolve(SourceRowResolverUserFixture::class, [['id' => 7], ['id' => 9]]);

        $this->assertSame([0 => null, 1 => null], $resolved);
    }

    #[Test]
    public function it_returns_all_null_without_an_entity_class(): void
    {
        $itemResolver = $this->createMock(ApiPlatformItemResolver::class);
        $itemResolver->expects($this->never())->method('resolve');

        $this->assertSame([0 => null], (new SourceRowResolver($itemResolver))->resolve(null, [['id' => 7]]));
    }

    #[Test]
    public function it_delegates_each_row_to_the_item_resolver(): void
    {
        $seven = new SourceRowResolverUserFixture(7);

        $itemResolver = $this->createMock(ApiPlatformItemResolver::class);
        $itemResolver->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnCallback(static fn (string $class, array $row): ?object => 7 === $row['id'] ? $seven : null);

        $resolved = (new SourceRowResolver($itemResolver))->resolve(
            SourceRowResolverUserFixture::class,
            [['id' => 7], ['id' => 9]],
        );

        $this->assertSame([0 => $seven, 1 => null], $resolved);
    }

    #[Test]
    public function it_keeps_non_array_rows_unresolved(): void
    {
        $itemResolver = $this->createMock(ApiPlatformItemResolver::class);
        $itemResolver->expects($this->never())->method('resolve');

        $resolved = (new SourceRowResolver($itemResolver))->resolve(SourceRowResolverUserFixture::class, ['not-a-row']);

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
