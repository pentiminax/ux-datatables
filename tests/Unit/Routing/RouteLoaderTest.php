<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Routing;

use Pentiminax\UX\DataTables\Routing\RouteLoader;
use PHPUnit\Framework\TestCase;

class RouteLoaderTest extends TestCase
{
    public function testLoadsBooleanToggleRoutes(): void
    {
        $routes = (new RouteLoader())->loadRoutes();

        $toggleRoute = $routes->get('ux_datatables_ajax_edit');
        $this->assertNotNull($toggleRoute);
        $this->assertSame('/datatables/ajax/edit', $toggleRoute->getPath());
        $this->assertSame('datatables.controller.ajax_edit', $toggleRoute->getDefault('_controller'));
        $this->assertSame(['POST', 'PATCH'], $toggleRoute->getMethods());

        $toggleByIdRoute = $routes->get('ux_datatables_ajax_edit_by_id');
        $this->assertNotNull($toggleByIdRoute);
        $this->assertSame('/datatables/boolean/{id}/toggle', $toggleByIdRoute->getPath());
        $this->assertSame('datatables.controller.ajax_edit', $toggleByIdRoute->getDefault('_controller'));
        $this->assertSame(['POST', 'PATCH'], $toggleByIdRoute->getMethods());
    }
}
