<?php

declare(strict_types=1);

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

        $routes->add('ux_datatables_ajax_delete', new Route(
            path: '/datatables/ajax/delete',
            defaults: ['_controller' => 'datatables.controller.ajax_delete'],
            methods: ['DELETE'],
        ));

        $routes->add('ux_datatables_ajax_edit_form', new Route(
            path: '/datatables/ajax/edit-form',
            defaults: ['_controller' => 'datatables.controller.ajax_edit_form'],
            methods: ['GET'],
        ));

        $routes->add('ux_datatables_ajax_edit_form_submit', new Route(
            path: '/datatables/ajax/edit-form',
            defaults: ['_controller' => 'datatables.controller.ajax_edit_form_submit'],
            methods: ['POST'],
        ));

        return $routes;
    }
}
