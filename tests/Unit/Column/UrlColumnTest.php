<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\UrlColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        $this->assertSame('_blank', $data['urlTarget']);
    }

    #[Test]
    public function it_stores_custom_target(): void
    {
        $data = UrlColumn::new('website')
            ->setTarget('_self')
            ->jsonSerialize();

        $this->assertSame('_self', $data['urlTarget']);
    }

    #[Test]
    public function it_stores_display_value_text(): void
    {
        $data = UrlColumn::new('website')
            ->setDisplayValue('Visit')
            ->jsonSerialize();

        $this->assertSame('Visit', $data['urlDisplayValue']);
    }

    #[Test]
    public function it_stores_route_params(): void
    {
        $data = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id'])
            ->jsonSerialize();

        $this->assertArrayNotHasKey('urlRouteName', $data);
        $this->assertSame(['id' => 'id'], $data['urlRouteParams']);
    }

    #[Test]
    public function it_stores_external_icon_flag(): void
    {
        $data = UrlColumn::new('website')
            ->showExternalIcon()
            ->jsonSerialize();

        $this->assertTrue($data['urlShowExternalIcon']);
    }

    #[Test]
    public function it_can_disable_external_icon(): void
    {
        $data = UrlColumn::new('website')
            ->showExternalIcon(false)
            ->jsonSerialize();

        $this->assertFalse($data['urlShowExternalIcon']);
    }

    #[Test]
    public function it_stores_url_template(): void
    {
        $data = UrlColumn::new('website')
            ->setUrlTemplate('/users/{id}')
            ->jsonSerialize();

        $this->assertSame('/users/{id}', $data['urlTemplate']);
    }

    #[Test]
    public function it_has_no_url_options_in_default_serialization(): void
    {
        $data = UrlColumn::new('website')->jsonSerialize();

        $this->assertArrayNotHasKey('urlTarget', $data);
        $this->assertArrayNotHasKey('urlDisplayValue', $data);
        $this->assertArrayNotHasKey('urlRouteName', $data);
        $this->assertArrayNotHasKey('urlRouteParams', $data);
        $this->assertArrayNotHasKey('urlTemplate', $data);
        $this->assertArrayNotHasKey('urlShowExternalIcon', $data);
    }

    #[Test]
    public function it_serializes_full_configuration(): void
    {
        $data = UrlColumn::new('website', 'User Link')
            ->route('app_user_show', ['id' => 'id'])
            ->setUrlTemplate('/users/{id}')
            ->openInNewTab()
            ->setDisplayValue('View')
            ->showExternalIcon()
            ->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertSame('_blank', $data['urlTarget']);
        $this->assertSame('View', $data['urlDisplayValue']);
        $this->assertSame(['id' => 'id'], $data['urlRouteParams']);
        $this->assertSame('/users/{id}', $data['urlTemplate']);
        $this->assertTrue($data['urlShowExternalIcon']);
    }

    #[Test]
    public function it_returns_stored_route_name(): void
    {
        $column = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id']);

        $this->assertSame('app_user_show', $column->getRouteName());
    }

    #[Test]
    public function it_returns_null_route_name_by_default(): void
    {
        $column = UrlColumn::new('website');

        $this->assertNull($column->getRouteName());
    }

    #[Test]
    public function it_returns_stored_route_params(): void
    {
        $column = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id', 'slug' => 'slug']);

        $this->assertSame(['id' => 'id', 'slug' => 'slug'], $column->getRouteParams());
    }
}
