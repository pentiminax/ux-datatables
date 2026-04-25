<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\UrlColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[CoversClass(UrlColumn::class)]
final class UrlColumnTest extends TestCase
{
    #[Test]
    public function it_creates_html_type_column(): void
    {
        $data = UrlColumn::new('website', 'Website')->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertSame('website', $data['data']);
        $this->assertSame('website', $data['name']);
        $this->assertSame('Website', $data['title']);
        $this->assertTrue($data['customOptions']['isUrl']);
    }

    #[Test]
    public function it_falls_back_to_name_as_title(): void
    {
        $data = UrlColumn::new('website')->jsonSerialize();

        $this->assertSame('website', $data['title']);
    }

    #[Test]
    public function it_sets_target_blank_when_opening_in_new_tab(): void
    {
        $data = UrlColumn::new('website')
            ->openInNewTab()
            ->jsonSerialize();

        $this->assertSame('_blank', $data['customOptions']['target']);
    }

    #[Test]
    public function it_stores_display_value_text(): void
    {
        $data = UrlColumn::new('website')
            ->setDisplayValue('Visit')
            ->jsonSerialize();

        $this->assertSame('Visit', $data['customOptions']['displayValue']);
    }

    #[Test]
    public function it_stores_external_icon_flag(): void
    {
        $data = UrlColumn::new('website')
            ->showExternalIcon()
            ->jsonSerialize();

        $this->assertTrue($data['customOptions']['showExternalIcon']);
    }

    #[Test]
    public function it_can_disable_external_icon(): void
    {
        $data = UrlColumn::new('website')
            ->showExternalIcon(false)
            ->jsonSerialize();

        $this->assertFalse($data['customOptions']['showExternalIcon']);
    }

    #[Test]
    public function it_resolves_static_url(): void
    {
        $column = UrlColumn::new('website')
            ->linkToUrl('/users');

        $this->assertSame('/users', $column->resolveUrl((object) ['id' => 7]));
        $this->assertTrue($column->hasUrlResolver());
        $this->assertArrayNotHasKey('url', $column->jsonSerialize()['customOptions']);
    }

    #[Test]
    public function it_resolves_url_from_callable(): void
    {
        $column = UrlColumn::new('website')
            ->linkToUrl(static fn (object $row): string => '/users/'.$row->id);

        $this->assertSame('/users/7', $column->resolveUrl((object) ['id' => 7]));
        $this->assertTrue($column->hasUrlResolver());
        $this->assertArrayNotHasKey('url', $column->jsonSerialize()['customOptions']);
    }

    #[Test]
    public function it_resolves_route_from_callable_params(): void
    {
        $column = UrlColumn::new('website')
            ->linkToRoute('app_user_show', static fn (object $row): array => ['id' => $row->id]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('app_user_show', ['id' => 7])
            ->willReturn('/users/7');

        $this->assertSame('/users/7', $column->resolveUrl((object) ['id' => 7], $urlGenerator));
        $this->assertTrue($column->hasUrlResolver());
        $this->assertArrayNotHasKey('routeName', $column->jsonSerialize()['customOptions']);
    }

    #[Test]
    public function it_resolves_route_from_array_params(): void
    {
        $column = UrlColumn::new('website')
            ->linkToRoute('app_user_index', ['type' => 'admin']);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('app_user_index', ['type' => 'admin'])
            ->willReturn('/users?type=admin');

        $this->assertSame('/users?type=admin', $column->resolveUrl((object) ['id' => 7], $urlGenerator));
    }

    #[Test]
    public function it_returns_null_for_blank_url(): void
    {
        $column = UrlColumn::new('website')
            ->linkToUrl(static fn (): string => '   ');

        $this->assertNull($column->resolveUrl((object) ['id' => 7]));
    }

    #[Test]
    public function it_fails_when_route_is_resolved_without_url_generator(): void
    {
        $column = UrlColumn::new('website')
            ->linkToRoute('app_user_show');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('UrlGeneratorInterface is required to resolve UrlColumn routes.');

        $column->resolveUrl((object) ['id' => 7]);
    }
}
