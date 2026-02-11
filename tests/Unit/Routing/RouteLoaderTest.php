<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Routing;

use Pentiminax\UX\DataTables\Routing\RouteLoader;
use PHPUnit\Framework\TestCase;

class RouteLoaderTest extends TestCase
{
    public function testLoadsAjaxEditRoute(): void
    {
        $routes = (new RouteLoader())->loadRoutes();

        $toggleRoute = $routes->get('ux_datatables_ajax_edit');
        $this->assertNotNull($toggleRoute);
        $this->assertSame('/datatables/ajax/edit', $toggleRoute->getPath());
        $this->assertSame('datatables.controller.ajax_edit', $toggleRoute->getDefault('_controller'));
        $this->assertSame(['POST', 'PATCH'], $toggleRoute->getMethods());

        $this->assertNull($routes->get('ux_datatables_ajax_edit_by_id'));
    }
}
