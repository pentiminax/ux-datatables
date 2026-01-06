<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\ScrollerExtension;
use PHPUnit\Framework\TestCase;

class ScrollerExtensionTest extends TestCase
{
    public function testScrollerExtension(): void
    {
        $extension = new ScrollerExtension();

        $this->assertTrue($extension->jsonSerialize());
    }
}
