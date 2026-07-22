<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Enum\IconSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IconColumn::class)]
final class IconColumnTest extends TestCase
{
    #[Test]
    public function it_creates_html_type_column(): void
    {
        $data = IconColumn::new('status', 'Status')->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertSame('status', $data['data']);
        $this->assertSame('status', $data['name']);
        $this->assertSame('Status', $data['title']);
    }

    #[Test]
    public function it_falls_back_to_name_as_title(): void
    {
        $data = IconColumn::new('status')->jsonSerialize();

        $this->assertSame('status', $data['title']);
    }

    #[Test]
    public function it_sets_is_icon_marker(): void
    {
        $data = IconColumn::new('status')->jsonSerialize();

        $this->assertTrue($data['customOptions']['isIcon']);
    }

    #[Test]
    public function it_stores_static_icon(): void
    {
        $data = IconColumn::new('status')
            ->icon('circle-check')
            ->jsonSerialize();

        $this->assertSame('circle-check', $data['customOptions']['icon']);
    }

    #[Test]
    public function it_stores_static_color(): void
    {
        $data = IconColumn::new('status')
            ->color('success')
            ->jsonSerialize();

        $this->assertSame('success', $data['customOptions']['color']);
    }

    #[Test]
    public function it_stores_size(): void
    {
        $data = IconColumn::new('status')
            ->size('lg')
            ->jsonSerialize();

        $this->assertSame('lg', $data['customOptions']['size']);
    }

    #[Test]
    public function it_stores_tooltips(): void
    {
        $data = IconColumn::new('status')
            ->tooltips(['active' => 'Compte actif'])
            ->jsonSerialize();

        $this->assertSame(['active' => 'Compte actif'], $data['customOptions']['tooltips']);
    }

    #[Test]
    public function it_configures_boolean_mode(): void
    {
        $data = IconColumn::new('isFeatured', 'Featured')
            ->boolean()
            ->trueIcon('circle-check')
            ->falseIcon('circle-x')
            ->trueColor('success')
            ->falseColor('danger')
            ->jsonSerialize();

        $this->assertTrue($data['customOptions']['boolean']);
        $this->assertSame('circle-check', $data['customOptions']['trueIcon']);
        $this->assertSame('circle-x', $data['customOptions']['falseIcon']);
        $this->assertSame('success', $data['customOptions']['trueColor']);
        $this->assertSame('danger', $data['customOptions']['falseColor']);
    }

    #[Test]
    public function it_accepts_icon_enum_equivalently_to_string(): void
    {
        $fromEnum = IconColumn::new('status')
            ->icon(Icon::CircleCheck)
            ->jsonSerialize();

        $fromString = IconColumn::new('status')
            ->icon('circle-check')
            ->jsonSerialize();

        $this->assertSame('circle-check', $fromEnum['customOptions']['icon']);
        $this->assertSame($fromString['customOptions'], $fromEnum['customOptions']);
    }

    #[Test]
    public function it_resolves_icon_and_color_via_callable(): void
    {
        $col = IconColumn::new('status')
            ->icon(static fn (string $s): Icon => match ($s) {
                'draft' => Icon::PencilLine,
                default => Icon::Circle,
            })
            ->color(static fn (string $s): string => 'draft' === $s ? 'warning' : 'secondary');

        $this->assertTrue($col->hasResolvers());
        $this->assertSame(['icon' => 'pencil-line', 'color' => 'warning'], $col->resolveIconData('draft'));

        $customOptions = $col->jsonSerialize()['customOptions'];
        $this->assertArrayNotHasKey('icon', $customOptions);
        $this->assertArrayNotHasKey('color', $customOptions);
    }

    #[Test]
    public function it_accepts_icon_size_enum(): void
    {
        $data = IconColumn::new('status')
            ->size(IconSize::Large)
            ->jsonSerialize();

        $this->assertSame('lg', $data['customOptions']['size']);
    }

    #[Test]
    public function it_serializes_full_configuration(): void
    {
        $data = IconColumn::new('status', 'Status')
            ->icon(Icon::CircleCheck)
            ->color('success')
            ->size(IconSize::Large)
            ->tooltips(['active' => 'Compte actif'])
            ->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertTrue($data['customOptions']['isIcon']);
        $this->assertSame('circle-check', $data['customOptions']['icon']);
        $this->assertSame('success', $data['customOptions']['color']);
        $this->assertSame('lg', $data['customOptions']['size']);
        $this->assertSame('Compte actif', $data['customOptions']['tooltips']['active']);
    }
}
