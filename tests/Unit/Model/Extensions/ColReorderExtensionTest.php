<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\Extensions;

use Pentiminax\UX\DataTables\Model\Extensions\ColReorderExtension;
use PHPUnit\Framework\TestCase;

class ColReorderExtensionTest extends TestCase
{
    public function testColReorderExtension(): void
    {
        $extension = new ColReorderExtension();

        $this->assertTrue($extension->jsonSerialize());
    }
}
