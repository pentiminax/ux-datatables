<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Routing;

use Pentiminax\UX\DataTables\Routing\RouteLoader;
use PHPUnit\Framework\TestCase;

class RouteLoaderTest extends TestCase
{
    public function testLoadsBooleanToggleRoutes(): void
    {
        $routes = (new RouteLoader())->loadRoutes();

        $toggleRoute = $routes->get('ux_datatables_boolean_toggle');
        $this->assertNotNull($toggleRoute);
        $this->assertSame('/_ux-datatables/boolean/toggle', $toggleRoute->getPath());
        $this->assertSame('datatables.controller.boolean_toggle', $toggleRoute->getDefault('_controller'));
        $this->assertSame(['POST', 'PATCH'], $toggleRoute->getMethods());

        $toggleByIdRoute = $routes->get('ux_datatables_boolean_toggle_by_id');
        $this->assertNotNull($toggleByIdRoute);
        $this->assertSame('/_ux-datatables/boolean/{id}/toggle', $toggleByIdRoute->getPath());
        $this->assertSame('datatables.controller.boolean_toggle', $toggleByIdRoute->getDefault('_controller'));
        $this->assertSame(['POST', 'PATCH'], $toggleByIdRoute->getMethods());
    }
}
