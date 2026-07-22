<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\RowMapper\Stage\IconColumnResolutionStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IconColumnResolutionStage::class)]
final class IconColumnResolutionStageTest extends TestCase
{
    #[Test]
    public function it_resolves_icon_and_color_via_callables(): void
    {
        $stage = new IconColumnResolutionStage();

        $result = $stage->process(
            ['status' => 'draft'],
            ['status' => 'draft'],
            [
                IconColumn::new('status')
                    ->icon(static fn (string $s): Icon => Icon::Clock)
                    ->color(static fn (string $s): string => 'warning'),
            ],
        );

        $this->assertSame(
            ['icon' => 'clock', 'color' => 'warning'],
            $result[IconColumnResolutionStage::ROW_ICONS_KEY]['status'],
        );
    }

    #[Test]
    public function it_passes_through_when_column_has_no_resolvers(): void
    {
        $stage = new IconColumnResolutionStage();

        $result = $stage->process(
            ['status' => 'draft'],
            ['status' => 'draft'],
            [IconColumn::new('status')->icon('clock')->color('warning')],
        );

        $this->assertArrayNotHasKey(IconColumnResolutionStage::ROW_ICONS_KEY, $result);
    }
}
