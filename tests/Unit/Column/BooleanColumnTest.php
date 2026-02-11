<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use PHPUnit\Framework\TestCase;

class BooleanColumnTest extends TestCase
{
    public function testDefaultBooleanColumnSerialization(): void
    {
        $data = BooleanColumn::new('active', 'Active')->jsonSerialize();

        $this->assertSame('badge', $data['booleanDisplayAs']);
        $this->assertSame('Yes', $data['booleanTrueLabel']);
        $this->assertSame('No', $data['booleanFalseLabel']);
        $this->assertSame('num', $data['type']);
    }

    public function testDisplayModeAndLabelsCanBeCustomized(): void
    {
        $data = BooleanColumn::new('active')
            ->displayAs(BooleanColumn::DISPLAY_AS_TOGGLE)
            ->setLabels('Active', 'Inactive')
            ->jsonSerialize();

        $this->assertSame('toggle', $data['booleanDisplayAs']);
        $this->assertSame('Active', $data['booleanTrueLabel']);
        $this->assertSame('Inactive', $data['booleanFalseLabel']);
    }

    public function testDisplayModeValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid display mode');

        BooleanColumn::new('active')->displayAs('chip');
    }
}
