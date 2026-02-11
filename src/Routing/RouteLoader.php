<?php

namespace Pentiminax\UX\DataTables\Routing;

use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteLoader implements RouteLoaderInterface
{
    public function loadRoutes(): RouteCollection
    {
        $routes = new RouteCollection();

        $routes->add('ux_datatables_boolean_toggle', new Route(
            path: '/_ux-datatables/boolean/toggle',
            defaults: ['_controller' => 'datatables.controller.boolean_toggle'],
            methods: ['POST', 'PATCH'],
        ));

        $routes->add('ux_datatables_boolean_toggle_by_id', new Route(
            path: '/_ux-datatables/boolean/{id}/toggle',
            defaults: ['_controller' => 'datatables.controller.boolean_toggle'],
            requirements: ['id' => '.+'],
            methods: ['POST', 'PATCH'],
        ));

        return $routes;
    }
}
