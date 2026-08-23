<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\ImageColumn;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(ImageColumn::class)]
final class ImageColumnTest extends DataTableTestCase
{
    #[Test]
    public function it_creates_html_type_column_with_lazy_loading_enabled(): void
    {
        $column = ImageColumn::new('avatar', 'Avatar');

        $this->assertColumnHeader($column, 'html', 'avatar', 'Avatar');
        $this->assertCustomOptions(['isImage' => true, 'lazy' => true], $column);
    }

    #[Test]
    #[DataProvider('provideCustomOptions')]
    public function it_serializes_custom_options(string $method, mixed $argument, string $option, mixed $expected): void
    {
        $column = ImageColumn::new('avatar')->{$method}($argument);

        $this->assertCustomOption($expected, $option, $column);
    }

    /**
     * @return iterable<string, array{string, mixed, string, mixed}>
     */
    public static function provideCustomOptions(): iterable
    {
        yield 'image width' => ['setImageWidth', 50, 'imageWidth', 50];
        yield 'image height' => ['setImageHeight', 50, 'imageHeight', 50];
        yield 'alt text' => ['setAlt', 'User avatar', 'alt', 'User avatar'];
        yield 'placeholder' => ['setPlaceholder', '/images/default.png', 'placeholder', '/images/default.png'];
        yield 'rounded enabled' => ['rounded', true, 'rounded', true];
        yield 'rounded disabled' => ['rounded', false, 'rounded', false];
        yield 'lazy disabled' => ['lazy', false, 'lazy', false];
        yield 'clickable enabled' => ['clickable', true, 'clickable', true];
        yield 'clickable disabled' => ['clickable', false, 'clickable', false];
    }

    #[Test]
    public function it_serializes_full_configuration(): void
    {
        $column = ImageColumn::new('avatar', 'Avatar')
            ->setImageWidth(50)
            ->setImageHeight(50)
            ->setAlt('Profile picture')
            ->setPlaceholder('/images/default.png')
            ->rounded()
            ->clickable();

        $this->assertCustomOptions([
            'isImage'     => true,
            'lazy'        => true,
            'imageWidth'  => 50,
            'imageHeight' => 50,
            'alt'         => 'Profile picture',
            'placeholder' => '/images/default.png',
            'rounded'     => true,
            'clickable'   => true,
        ], $column);
    }
}
