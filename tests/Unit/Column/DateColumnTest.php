<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\DateColumn;
use PHPUnit\Framework\TestCase;

final class DateColumnTest extends TestCase
{
    public function testGetFormatReturnsDefaultWhenNoFormatProvided(): void
    {
        $column = DateColumn::new('createdAt');

        $this->assertSame(DateColumn::DEFAULT_DATE_FORMAT, $column->getFormat());
    }

    public function testJsonSerializeExposesDateFormat(): void
    {
        $column = DateColumn::new('createdAt')->setFormat('d/m/Y');

        $this->assertSame('d/m/Y', $column->jsonSerialize()[DateColumn::OPTION_DATE_FORMAT]);
    }
}
