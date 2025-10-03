<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Options;

use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Options\LayoutOption;
use PHPUnit\Framework\TestCase;

class LayoutOptionTest extends TestCase
{
    public function testLayoutOption(): void
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