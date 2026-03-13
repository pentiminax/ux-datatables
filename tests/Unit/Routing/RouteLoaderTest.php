<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Routing;

use Pentiminax\UX\DataTables\Routing\RouteLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RouteLoader::class)]
final class RouteLoaderTest extends TestCase
{
    #[Test]
    public function it_loads_ajax_edit_route(): void
    {
        $routes = (new RouteLoader())->loadRoutes();

        $toggleRoute = $routes->get('ux_datatables_ajax_edit');
        $this->assertNotNull($toggleRoute);
        $this->assertSame('/datatables/ajax/edit', $toggleRoute->getPath());
        $this->assertSame('datatables.controller.ajax_edit', $toggleRoute->getDefault('_controller'));
        $this->assertSame(['POST', 'PATCH'], $toggleRoute->getMethods());

        $this->assertNull($routes->get('ux_datatables_ajax_edit_by_id'));
    }

    #[Test]
    public function it_loads_ajax_edit_form_routes(): void
    {
        $routes = (new RouteLoader())->loadRoutes();

        $getRoute = $routes->get('ux_datatables_ajax_edit_form');
        $this->assertNotNull($getRoute);
        $this->assertSame('/datatables/ajax/edit-form', $getRoute->getPath());
        $this->assertSame('datatables.controller.ajax_edit_form', $getRoute->getDefault('_controller'));
        $this->assertSame(['GET'], $getRoute->getMethods());

        $postRoute = $routes->get('ux_datatables_ajax_edit_form_submit');
        $this->assertNotNull($postRoute);
        $this->assertSame('/datatables/ajax/edit-form', $postRoute->getPath());
        $this->assertSame('datatables.controller.ajax_edit_form_submit', $postRoute->getDefault('_controller'));
        $this->assertSame(['POST'], $postRoute->getMethods());
    }
}
