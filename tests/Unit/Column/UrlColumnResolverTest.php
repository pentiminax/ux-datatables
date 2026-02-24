<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Column\UrlColumnResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class UrlColumnResolverTest extends TestCase
{
    public function testResolvesUrlTemplateForRouteBasedColumn(): void
    {
        $column = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id']);

        $resolver = new UrlColumnResolver($this->createRouter('/users/{id}'));
        $resolver->resolveRoutes([$column]);

        $data = $column->jsonSerialize();
        $this->assertSame('/users/{id}', $data['urlTemplate']);
    }

    public function testSkipsUrlColumnsWithoutRoute(): void
    {
        $column = UrlColumn::new('website');

        $resolver = new UrlColumnResolver($this->createRouter('/unused'));
        $resolver->resolveRoutes([$column]);

        $data = $column->jsonSerialize();
        $this->assertArrayNotHasKey('urlTemplate', $data);
    }

    public function testSkipsNonUrlColumns(): void
    {
        $column = TextColumn::new('name');

        $resolver = new UrlColumnResolver($this->createRouter('/unused'));
        $resolver->resolveRoutes([$column]);

        $data = $column->jsonSerialize();
        $this->assertArrayNotHasKey('urlTemplate', $data);
    }

    public function testThrowsOnUnknownRouteName(): void
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

    public function testHandlesBaseUrlFromRequestContext(): void
    {
        $column = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id']);

        $resolver = new UrlColumnResolver($this->createRouter('/users/{id}', '/app'));
        $resolver->resolveRoutes([$column]);

        $data = $column->jsonSerialize();
        $this->assertSame('/app/users/{id}', $data['urlTemplate']);
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
