<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Enum\IconSize;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(IconColumn::class)]
final class IconColumnTest extends DataTableTestCase
{
    #[Test]
    public function it_creates_html_type_column_with_only_the_icon_marker(): void
    {
        $column = IconColumn::new('status', 'Status');

        $this->assertColumnHeader($column, 'html', 'status', 'Status');
        $this->assertCustomOptions(['isIcon' => true], $column);
    }

    #[Test]
    #[DataProvider('provideCustomOptions')]
    public function it_serializes_custom_options(string $method, mixed $argument, string $option, mixed $expected): void
    {
        $column = IconColumn::new('status')->{$method}($argument);

        $this->assertCustomOption($expected, $option, $column);
    }

    /**
     * @return iterable<string, array{string, mixed, string, mixed}>
     */
    public static function provideCustomOptions(): iterable
    {
        yield 'static icon' => ['icon', 'circle-check', 'icon', 'circle-check'];
        yield 'icon enum' => ['icon', Icon::CircleCheck, 'icon', 'circle-check'];
        yield 'static color' => ['color', 'success', 'color', 'success'];
        yield 'size' => ['size', 'lg', 'size', 'lg'];
        yield 'size enum' => ['size', IconSize::Large, 'size', 'lg'];
        yield 'tooltips' => ['tooltips', ['active' => 'Compte actif'], 'tooltips', ['active' => 'Compte actif']];
    }

    #[Test]
    public function it_configures_boolean_mode(): void
    {
        $column = IconColumn::new('isFeatured', 'Featured')
            ->boolean()
            ->trueIcon('circle-check')
            ->falseIcon('circle-x')
            ->trueColor('success')
            ->falseColor('danger');

        $this->assertCustomOptions([
            'isIcon'     => true,
            'boolean'    => true,
            'trueIcon'   => 'circle-check',
            'falseIcon'  => 'circle-x',
            'trueColor'  => 'success',
            'falseColor' => 'danger',
        ], $column);
    }

    #[Test]
    public function it_resolves_icon_and_color_via_callable(): void
    {
        $column = IconColumn::new('status')
            ->icon(static fn (string $state): Icon => 'draft' === $state ? Icon::PencilLine : Icon::Circle)
            ->color(static fn (string $state): string => 'draft' === $state ? 'warning' : 'secondary');

        $this->assertTrue($column->hasResolvers());
        $this->assertSame(['icon' => 'pencil-line', 'color' => 'warning'], $column->resolveIconData('draft'));
        $this->assertCustomOptions(['isIcon' => true], $column);
    }

    #[Test]
    public function it_omits_icon_when_callable_returns_null(): void
    {
        $column = IconColumn::new('status')
            ->icon(static fn (string $state): ?Icon => 'draft' === $state ? Icon::PencilLine : null)
            ->color(static fn (string $state): string => 'secondary');

        $this->assertSame(['color' => 'secondary'], $column->resolveIconData('published'));
    }

    #[Test]
    public function it_clears_resolvers_when_static_values_are_set_afterwards(): void
    {
        $column = IconColumn::new('status')
            ->icon(static fn (string $state): Icon => Icon::Circle)
            ->color(static fn (string $state): string => 'warning')
            ->icon('circle-check')
            ->color('success');

        $this->assertFalse($column->hasResolvers());
        $this->assertCustomOptions(['isIcon' => true, 'icon' => 'circle-check', 'color' => 'success'], $column);
    }

    #[Test]
    public function it_drops_static_icon_when_a_callable_is_set_afterwards(): void
    {
        $column = IconColumn::new('status')
            ->icon('circle-check')
            ->icon(static fn (string $state): Icon => Icon::Circle);

        $this->assertTrue($column->hasResolvers());
        $this->assertNoCustomOption('icon', $column);
    }
}
