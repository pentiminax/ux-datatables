<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\ImageColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ImageColumn::class)]
final class ImageColumnTest extends TestCase
{
    #[Test]
    public function it_creates_html_type_column(): void
    {
        $data = ImageColumn::new('avatar', 'Avatar')->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertSame('avatar', $data['data']);
        $this->assertSame('avatar', $data['name']);
        $this->assertSame('Avatar', $data['title']);
    }

    #[Test]
    public function it_falls_back_to_name_as_title(): void
    {
        $data = ImageColumn::new('avatar')->jsonSerialize();

        $this->assertSame('avatar', $data['title']);
    }

    #[Test]
    public function it_sets_is_image_marker_and_lazy_by_default(): void
    {
        $data = ImageColumn::new('avatar')->jsonSerialize();

        $this->assertTrue($data['customOptions']['isImage']);
        $this->assertTrue($data['customOptions']['lazy']);
    }

    #[Test]
    public function it_stores_image_width(): void
    {
        $data = ImageColumn::new('avatar')
            ->setImageWidth(50)
            ->jsonSerialize();

        $this->assertSame(50, $data['customOptions']['imageWidth']);
    }

    #[Test]
    public function it_stores_image_height(): void
    {
        $data = ImageColumn::new('avatar')
            ->setImageHeight(50)
            ->jsonSerialize();

        $this->assertSame(50, $data['customOptions']['imageHeight']);
    }

    #[Test]
    public function it_stores_alt_text(): void
    {
        $data = ImageColumn::new('avatar')
            ->setAlt('User avatar')
            ->jsonSerialize();

        $this->assertSame('User avatar', $data['customOptions']['alt']);
    }

    #[Test]
    public function it_stores_placeholder_url(): void
    {
        $data = ImageColumn::new('avatar')
            ->setPlaceholder('/images/default.png')
            ->jsonSerialize();

        $this->assertSame('/images/default.png', $data['customOptions']['placeholder']);
    }

    #[Test]
    public function it_enables_rounded(): void
    {
        $data = ImageColumn::new('avatar')
            ->rounded()
            ->jsonSerialize();

        $this->assertTrue($data['customOptions']['rounded']);
    }

    #[Test]
    public function it_can_disable_rounded(): void
    {
        $data = ImageColumn::new('avatar')
            ->rounded(false)
            ->jsonSerialize();

        $this->assertFalse($data['customOptions']['rounded']);
    }

    #[Test]
    public function it_can_disable_lazy_loading(): void
    {
        $data = ImageColumn::new('avatar')
            ->lazy(false)
            ->jsonSerialize();

        $this->assertFalse($data['customOptions']['lazy']);
    }

    #[Test]
    public function it_enables_clickable(): void
    {
        $data = ImageColumn::new('avatar')
            ->clickable()
            ->jsonSerialize();

        $this->assertTrue($data['customOptions']['clickable']);
    }

    #[Test]
    public function it_can_disable_clickable(): void
    {
        $data = ImageColumn::new('avatar')
            ->clickable(false)
            ->jsonSerialize();

        $this->assertFalse($data['customOptions']['clickable']);
    }

    #[Test]
    public function it_serializes_full_configuration(): void
    {
        $data = ImageColumn::new('avatar', 'Avatar')
            ->setImageWidth(50)
            ->setImageHeight(50)
            ->setAlt('Profile picture')
            ->setPlaceholder('/images/default.png')
            ->rounded()
            ->clickable()
            ->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertTrue($data['customOptions']['isImage']);
        $this->assertTrue($data['customOptions']['lazy']);
        $this->assertSame(50, $data['customOptions']['imageWidth']);
        $this->assertSame(50, $data['customOptions']['imageHeight']);
        $this->assertSame('Profile picture', $data['customOptions']['alt']);
        $this->assertSame('/images/default.png', $data['customOptions']['placeholder']);
        $this->assertTrue($data['customOptions']['rounded']);
        $this->assertTrue($data['customOptions']['clickable']);
    }
}
