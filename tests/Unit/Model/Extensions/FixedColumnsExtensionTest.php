<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\FixedColumnsExtension;
use PHPUnit\Framework\TestCase;

class FixedColumnsExtensionTest extends TestCase
{
    public function testFixedColumnsExtension(): void
    {
        $extension = new FixedColumnsExtension();

        $expectedArray = [
            'start' => 1,
            'end'   => 0,
        ];

        $this->assertEquals($expectedArray, $extension->jsonSerialize());
    }

    public function testFixedColumnsExtensionWithCustomValues(): void
    {
        $extension = new FixedColumnsExtension(start: 2, end: 1);

        $expectedArray = [
            'start' => 2,
            'end'   => 1,
        ];

        $this->assertEquals($expectedArray, $extension->jsonSerialize());
    }
}
