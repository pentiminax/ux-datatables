<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use PHPUnit\Framework\TestCase;

class BooleanColumnTest extends TestCase
{
    public function testDefaultBooleanColumnSerialization(): void
    {
        $data = BooleanColumn::new('active', 'Active')->jsonSerialize();

        $this->assertTrue($data['booleanRenderAsSwitch']);
        $this->assertFalse($data['booleanDefaultState']);
        $this->assertSame('num', $data['type']);
    }

    public function testSwitchStateAndAjaxCanBeConfigured(): void
    {
        $data = BooleanColumn::new('active')
            ->renderAsSwitch(true)
            ->setToggleAjax('/admin/users/toggle', 'uuid', 'post')
            ->jsonSerialize();

        $this->assertTrue($data['booleanRenderAsSwitch']);
        $this->assertTrue($data['booleanDefaultState']);
        $this->assertSame('/admin/users/toggle', $data['booleanToggleUrl']);
        $this->assertSame('uuid', $data['booleanToggleIdField']);
        $this->assertSame('POST', $data['booleanToggleMethod']);
    }

    public function testRenderAsSwitchCanSetDefaultOffState(): void
    {
        $data = BooleanColumn::new('active')
            ->renderAsSwitch(false)
            ->jsonSerialize();

        $this->assertTrue($data['booleanRenderAsSwitch']);
        $this->assertFalse($data['booleanDefaultState']);
    }
}
