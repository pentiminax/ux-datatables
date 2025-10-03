<?php

namespace Pentiminax\UX\DataTables\Tests\Unit;

use Pentiminax\UX\DataTables\Tests\Kernel\TwigAppKernel;
use PHPUnit\Framework\TestCase;

class DataTablesBundleTest extends TestCase
{
    public function testBootKernel(): void
    {
        $kernel = new TwigAppKernel('test', true);

        $kernel->boot();

        $this->assertArrayHasKey('DataTablesBundle', $kernel->getBundles());

        $kernel->shutdown();
    }
}
