<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use PHPUnit\Framework\TestCase;

class SelectExtensionTest extends TestCase
{
    public function testToArray(): void
    {
        $extension = new SelectExtension();

        $serializedExtension = $extension->jsonSerialize();

        $expectedArray = [
            'blurable'       => false,
            'className'      => 'selected',
            'info'           => true,
            'items'          => 'row',
            'keys'           => false,
            'style'          => 'single',
            'toggleable'     => true,
            'headerCheckbox' => false,
            'withCheckbox'   => false,
        ];

        $this->assertEquals($expectedArray, $serializedExtension);
    }

    public function testStyle(): void
    {
        $extension = new SelectExtension(SelectStyle::MULTI);

        $this->assertEquals('multi', $extension->jsonSerialize()['style']);
    }
}
