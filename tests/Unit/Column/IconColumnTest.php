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
    public function it_stores_icons_map(): void
    {
        $data = IconColumn::new('status')
            ->icons(['active' => 'circle-check', 'pending' => 'clock'])
            ->jsonSerialize();

        $this->assertSame(
            ['active' => 'circle-check', 'pending' => 'clock'],
            $data['customOptions']['icons'],
        );
    }

    #[Test]
    public function it_stores_default_icon(): void
    {
        $data = IconColumn::new('status')
            ->defaultIcon('circle')
            ->jsonSerialize();

        $this->assertSame('circle', $data['customOptions']['defaultIcon']);
    }

    #[Test]
    public function it_stores_colors_and_default_color(): void
    {
        $data = IconColumn::new('status')
            ->colors(['active' => 'success', 'pending' => 'warning'])
            ->defaultColor('gray')
            ->jsonSerialize();

        $this->assertSame(
            ['active' => 'success', 'pending' => 'warning'],
            $data['customOptions']['colors'],
        );
        $this->assertSame('gray', $data['customOptions']['defaultColor']);
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
            ->icons(['active' => Icon::CircleCheck])
            ->defaultIcon(Icon::Circle)
            ->jsonSerialize();

        $fromString = IconColumn::new('status')
            ->icons(['active' => 'circle-check'])
            ->defaultIcon('circle')
            ->jsonSerialize();

        $this->assertSame('circle-check', $fromEnum['customOptions']['icons']['active']);
        $this->assertSame('circle', $fromEnum['customOptions']['defaultIcon']);
        $this->assertSame($fromString['customOptions'], $fromEnum['customOptions']);
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
            ->icons(['active' => Icon::CircleCheck, 'archived' => 'archive'])
            ->defaultIcon('circle')
            ->colors(['active' => 'success', 'archived' => 'gray'])
            ->defaultColor('secondary')
            ->size(IconSize::Large)
            ->tooltips(['active' => 'Compte actif'])
            ->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertTrue($data['customOptions']['isIcon']);
        $this->assertSame('circle-check', $data['customOptions']['icons']['active']);
        $this->assertSame('archive', $data['customOptions']['icons']['archived']);
        $this->assertSame('circle', $data['customOptions']['defaultIcon']);
        $this->assertSame('success', $data['customOptions']['colors']['active']);
        $this->assertSame('secondary', $data['customOptions']['defaultColor']);
        $this->assertSame('lg', $data['customOptions']['size']);
        $this->assertSame('Compte actif', $data['customOptions']['tooltips']['active']);
    }
}
