<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Routing;

use Pentiminax\UX\DataTables\Routing\RouteLoader;
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
    /**
     * @return iterable<string, array{string, string, string, list<string>}>
     */
    public static function ajaxRoutes(): iterable
    {
        yield 'data' => ['ux_datatables_ajax_data', '/datatables/ajax/data', 'datatables.controller.ajax_data', ['GET']];
        yield 'templates' => ['ux_datatables_ajax_templates', '/datatables/ajax/templates', 'datatables.controller.ajax_templates', ['POST']];
        yield 'edit' => ['ux_datatables_ajax_edit', '/datatables/ajax/edit', 'datatables.controller.ajax_edit', ['POST', 'PATCH']];
        yield 'delete' => ['ux_datatables_ajax_delete', '/datatables/ajax/delete', 'datatables.controller.ajax_delete', ['DELETE']];
        yield 'edit form' => ['ux_datatables_ajax_edit_form', '/datatables/ajax/edit-form/view', 'datatables.controller.ajax_edit_form', ['POST']];
        yield 'edit form submit' => ['ux_datatables_ajax_edit_form_submit', '/datatables/ajax/edit-form', 'datatables.controller.ajax_edit_form_submit', ['POST']];
        yield 'detail' => ['ux_datatables_ajax_detail', '/datatables/ajax/detail', 'datatables.controller.ajax_detail', ['POST']];
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
}
