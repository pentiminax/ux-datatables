<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\KeyTableExtension;
use PHPUnit\Framework\TestCase;

class KeyTableExtensionTest extends TestCase
{
    public function testResponsiveExtension(): void
    {
        $extension = new KeyTableExtension();

        $this->assertTrue($extension->jsonSerialize());
    }
}
