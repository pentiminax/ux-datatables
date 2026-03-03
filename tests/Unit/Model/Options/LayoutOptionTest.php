<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Options;

use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Options\LayoutOption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LayoutOption::class)]
final class LayoutOptionTest extends TestCase
{
    #[Test]
    public function it_stores_layout_features(): void
    {
        $layoutOption = new LayoutOption(
            table: new DataTable('testTable'),
            topStart: Feature::PAGE_LENGTH,
            topEnd: Feature::SEARCH,
            bottomStart: Feature::INFO,
            bottomEnd: Feature::PAGING,
        );

        $this->assertEquals(Feature::PAGE_LENGTH->value, $layoutOption->topStart->value);
        $this->assertEquals(Feature::SEARCH->value, $layoutOption->topEnd->value);
        $this->assertEquals(Feature::INFO->value, $layoutOption->bottomStart->value);
        $this->assertEquals(Feature::PAGING->value, $layoutOption->bottomEnd->value);
    }
}
