<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Routing;

use Pentiminax\UX\DataTables\Routing\RouteLoader;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DEFAULT_ROW_ACTION_URLS (assets/src/functions/rowActionUrl.ts) copies the route loader's paths
 * for pages cached before the payload carried rowActionUrls. Nothing else ties the copy to the
 * routes, so a path changed on the PHP side would break those pages silently.
 *
 * @internal
 */
#[CoversNothing]
final class RowActionUrlConsistencyTest extends TestCase
{
    private const ROUTES = [
        'delete'         => 'ux_datatables_ajax_delete',
        'toggle'         => 'ux_datatables_ajax_edit',
        'detail'         => 'ux_datatables_ajax_detail',
        'editForm'       => 'ux_datatables_ajax_edit_form',
        'editFormSubmit' => 'ux_datatables_ajax_edit_form_submit',
    ];

    #[Test]
    public function it_matches_the_frontend_default_row_action_urls(): void
    {
        $tsPath = \dirname(__DIR__, 3).'/assets/src/functions/rowActionUrl.ts';
        $source = file_get_contents($tsPath);

        $this->assertNotFalse($source, "Could not read {$tsPath}.");

        preg_match_all("/^\s+(\w+):\s*'([^']+)',?$/m", $source, $matches);
        $frontend = array_combine($matches[1], $matches[2]);

        $routes = (new RouteLoader())->loadRoutes();
        $php    = [];
        foreach (self::ROUTES as $name => $route) {
            $php[$name] = $routes->get($route)?->getPath();
        }

        $this->assertSame(
            $php,
            $frontend,
            'The route loader (src/Routing/RouteLoader.php) and DEFAULT_ROW_ACTION_URLS '
                .'(assets/src/functions/rowActionUrl.ts) have drifted apart -- change a path in both places together.',
        );
    }
}
