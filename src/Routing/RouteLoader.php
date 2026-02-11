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

        $routes->add('ux_datatables_ajax_edit', new Route(
            path: '/datatables/ajax/edit',
            defaults: ['_controller' => 'datatables.controller.ajax_edit'],
            methods: ['POST', 'PATCH'],
        ));

        return $routes;
    }
}
