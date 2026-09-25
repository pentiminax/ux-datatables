<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Routing;

use Pentiminax\UX\DataTables\Routing\RouteLoader;
use Pentiminax\UX\DataTables\Tests\Support\BootsTwigKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RouteLoader::class)]
final class RouteLoaderTest extends TestCase
{
    use BootsTwigKernel;

    /**
     * @return iterable<string, array{string, string, string, list<string>}>
     */
    public static function ajaxRoutes(): iterable
    {
        yield 'data' => ['ux_datatables_ajax_data', '/datatables/ajax/data', 'datatables.controller.ajax_data', ['GET']];
        yield 'edit' => ['ux_datatables_ajax_edit', '/datatables/ajax/edit', 'datatables.controller.ajax_edit', ['POST', 'PATCH']];
        yield 'delete' => ['ux_datatables_ajax_delete', '/datatables/ajax/delete', 'datatables.controller.ajax_delete', ['DELETE']];
        yield 'edit form' => ['ux_datatables_ajax_edit_form', '/datatables/ajax/edit-form/view', 'datatables.controller.ajax_edit_form', ['POST']];
        yield 'edit form submit' => ['ux_datatables_ajax_edit_form_submit', '/datatables/ajax/edit-form', 'datatables.controller.ajax_edit_form_submit', ['POST']];
        yield 'detail' => ['ux_datatables_ajax_detail', '/datatables/ajax/detail', 'datatables.controller.ajax_detail', ['POST']];
        yield 'bulk' => ['ux_datatables_ajax_bulk', '/datatables/ajax/bulk', 'datatables.controller.ajax_bulk', ['POST']];
        yield 'export' => ['ux_datatables_ajax_export', '/datatables/ajax/export', 'datatables.controller.ajax_export', ['POST']];
    }

    /**
     * @param list<string> $methods
     */
    #[Test]
    #[DataProvider('ajaxRoutes')]
    public function it_loads_the_ajax_route(string $name, string $path, string $controller, array $methods): void
    {
        $route = (new RouteLoader())->loadRoutes()->get($name);

        $this->assertNotNull($route);
        $this->assertSame($path, $route->getPath());
        $this->assertSame($controller, $route->getDefault('_controller'));
        $this->assertSame($methods, $route->getMethods());
    }

    #[Test]
    public function it_does_not_load_the_removed_edit_by_id_route(): void
    {
        $this->assertNull((new RouteLoader())->loadRoutes()->get('ux_datatables_ajax_edit_by_id'));
    }

    /**
     * The route table and the service definitions are written in two different files, so a
     * service renamed in config/ leaves the route pointing at an id nothing answers to. The
     * provider above pins the URLs the bundle publishes; this pins that they resolve.
     */
    #[Test]
    public function every_route_points_at_a_registered_controller(): void
    {
        $routes = (new RouteLoader())->loadRoutes();

        $this->assertCount(\count(iterator_to_array(self::ajaxRoutes())), $routes);

        foreach ($routes as $name => $route) {
            $id = $route->getDefault('_controller');

            $this->assertIsString($id);
            $this->assertTrue(
                $this->container->has($id),
                \sprintf('Route "%s" points at service "%s", which no configuration file registers.', $name, $id),
            );
            $this->assertIsCallable(
                $this->container->get($id),
                \sprintf('Route "%s" points at service "%s", which does not resolve to an invokable controller.', $name, $id),
            );
        }
    }
}
