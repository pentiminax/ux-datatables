<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\RouteAwareColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(UrlColumnResolver::class)]
final class UrlColumnResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_url_template_for_route_based_column(): void
    {
        $column = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id']);

        $resolver = new UrlColumnResolver($this->createRouter('/users/{id}'));
        $resolver->resolveRoutes([$column]);

        $data = $column->jsonSerialize();
        $this->assertSame('/users/{id}', $data['customOptions']['template']);
    }

    #[Test]
    public function it_skips_url_columns_without_route(): void
    {
        $column = UrlColumn::new('website');

        $resolver = new UrlColumnResolver($this->createRouter('/unused'));
        $resolver->resolveRoutes([$column]);

        $data = $column->jsonSerialize();
        $this->assertArrayNotHasKey('urlTemplate', $data);
    }

    #[Test]
    public function it_skips_non_url_columns(): void
    {
        $column = TextColumn::new('name');

        $resolver = new UrlColumnResolver($this->createRouter('/unused'));
        $resolver->resolveRoutes([$column]);

        $data = $column->jsonSerialize();
        $this->assertArrayNotHasKey('urlTemplate', $data);
    }

    #[Test]
    public function it_throws_on_unknown_route_name(): void
    {
        $column = UrlColumn::new('website')
            ->route('nonexistent_route', ['id' => 'id']);

        $routeCollection = new RouteCollection();

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);

        $resolver = new UrlColumnResolver($router);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route "nonexistent_route" does not exist.');

        $resolver->resolveRoutes([$column]);
    }

    #[Test]
    public function it_handles_base_url_from_request_context(): void
    {
        $column = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id']);

        $resolver = new UrlColumnResolver($this->createRouter('/users/{id}', '/app'));
        $resolver->resolveRoutes([$column]);

        $data = $column->jsonSerialize();
        $this->assertSame('/app/users/{id}', $data['customOptions']['template']);
    }

    #[Test]
    public function it_resolves_routes_for_custom_route_aware_columns(): void
    {
        $column = new class extends AbstractColumn implements RouteAwareColumnInterface {
            private ?string $routeName = 'app_user_show';
            private ?string $template  = null;

            public function __construct()
            {
                $this->setName('custom');
                $this->setTitle('custom');
                $this->setType(ColumnType::HTML);
            }

            public function getRouteName(): ?string
            {
                return $this->routeName;
            }

            public function setUrlTemplate(string $template): static
            {
                $this->template = $template;

                return $this;
            }

            public function resolvedTemplate(): ?string
            {
                return $this->template;
            }
        };

        $resolver = new UrlColumnResolver($this->createRouter('/custom/{id}'));
        $resolver->resolveRoutes([$column]);

        $this->assertSame('/custom/{id}', $column->resolvedTemplate());
    }

    private function createRouter(string $path, string $baseUrl = ''): RouterInterface
    {
        $route = new Route($path);

        $routeCollection = new RouteCollection();
        $routeCollection->add('app_user_show', $route);

        $context = new RequestContext($baseUrl);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);
        $router->method('getContext')->willReturn($context);

        return $router;
    }
}
