<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\ResponsiveExtension;
use PHPUnit\Framework\TestCase;

class ResponsiveExtensionTest extends TestCase
{
    public function testResponsiveExtension(): void
    {
        $extension = new ResponsiveExtension();

        $this->assertTrue($extension->jsonSerialize());
    }
}