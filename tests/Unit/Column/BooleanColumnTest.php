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
            ->setToggleAjax('uuid', 'post')
            ->jsonSerialize();

        $this->assertTrue($data['booleanRenderAsSwitch']);
        $this->assertTrue($data['booleanDefaultState']);
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

    public function testEntityClassCanBeConfigured(): void
    {
        $data = BooleanColumn::new('active')
            ->setEntityClass('App\\Entity\\User')
            ->jsonSerialize();

        $this->assertSame('App\\Entity\\User', $data['booleanToggleEntityClass']);
    }
}
