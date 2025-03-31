<?php

namespace Pentiminax\UX\DataTables\Tests\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use PHPUnit\Framework\TestCase;

class SelectExtensionTest extends TestCase
{
    public function testToArray(): void
    {
        $extension = new SelectExtension();

        $this->assertEquals(['style' => 'single'], $extension->toArray());
    }

    public function testStyle(): void
    {
        $extension = new SelectExtension(SelectStyle::MULTI);

        $this->assertEquals(['style' => 'multi'], $extension->toArray());
    }
}