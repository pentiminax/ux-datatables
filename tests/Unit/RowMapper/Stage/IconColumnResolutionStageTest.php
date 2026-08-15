<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\RowMapper\Stage\IconColumnResolutionStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IconColumnResolutionStage::class)]
final class IconColumnResolutionStageTest extends TestCase
{
    /**
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('iconColumnProvider')]
    public function it_exposes_resolved_icon_data_only_for_columns_with_resolvers(array $columns, array $expected): void
    {
        $result = (new IconColumnResolutionStage())->process(['status' => 'draft'], ['status' => 'draft'], $columns);

        $this->assertSame($expected, $result);
    }

    public static function iconColumnProvider(): iterable
    {
        yield 'icon and color resolved via callables' => [
            [
                IconColumn::new('status')
                    ->icon(static fn (string $s): Icon => Icon::Clock)
                    ->color(static fn (string $s): string => 'warning'),
            ],
            [
                'status'                                 => 'draft',
                IconColumnResolutionStage::ROW_ICONS_KEY => [
                    'status' => ['icon' => 'clock', 'color' => 'warning'],
                ],
            ],
        ];

        yield 'static icon and color are not exposed per row' => [
            [IconColumn::new('status')->icon('clock')->color('warning')],
            ['status' => 'draft'],
        ];
    }
}
