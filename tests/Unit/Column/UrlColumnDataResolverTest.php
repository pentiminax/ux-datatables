<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(UrlColumnDataResolver::class)]
final class UrlColumnDataResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_route_url_for_row(): void
    {
        $column = UrlColumn::new('profile')
            ->linkToRoute('app_user_show', static fn (object $user): array => ['id' => $user->id]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('app_user_show', ['id' => 7])
            ->willReturn('/users/7');

        $row = (new UrlColumnDataResolver($urlGenerator))
            ->resolveRow(['profile' => 'Jane'], (object) ['id' => 7], [$column]);

        $this->assertSame('/users/7', $row['__ux_datatables_urls']['profile']);
        $this->assertSame('Jane', $row['profile']);
    }

    #[Test]
    public function it_resolves_callable_url_for_array_row(): void
    {
        $column = UrlColumn::new('website')
            ->linkToUrl(static fn (array $row): string => 'https://example.com/users/'.$row['slug']);

        $row = (new UrlColumnDataResolver())
            ->resolveRow(['website' => 'Profile'], ['slug' => 'jane'], [$column]);

        $this->assertSame('https://example.com/users/jane', $row['__ux_datatables_urls']['website']);
    }

    #[Test]
    #[DataProvider('provideColumnsWithoutResolvableUrl')]
    public function it_skips_columns_without_resolvable_url(ColumnInterface $column, array $row): void
    {
        $row = (new UrlColumnDataResolver())->resolveRow($row, ['slug' => 'jane'], [$column]);

        $this->assertArrayNotHasKey('__ux_datatables_urls', $row);
    }

    /**
     * @return iterable<string, array{ColumnInterface, array<string, mixed>}>
     */
    public static function provideColumnsWithoutResolvableUrl(): iterable
    {
        yield 'url column without resolver' => [UrlColumn::new('website'), ['website' => 'https://example.com']];
        yield 'non url column' => [TextColumn::new('name'), ['name' => 'Jane']];
    }
}
