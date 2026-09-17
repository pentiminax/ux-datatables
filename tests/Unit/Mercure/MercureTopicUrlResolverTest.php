<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Mercure\MercureTopicUrlResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(MercureTopicUrlResolver::class)]
final class MercureTopicUrlResolverTest extends TestCase
{
    #[Test]
    #[DataProvider('provideRequestContexts')]
    public function it_builds_the_absolute_url_the_url_generator_builds(
        RequestContext $context,
        string $expectedOrigin,
    ): void {
        $resolver = new MercureTopicUrlResolver($this->routerWithContext($context));

        $this->assertSame($expectedOrigin.'/api/books/{id}', $resolver->absoluteUrl('/api/books/{id}'));

        // API Platform publishes the item IRI through this exact generator, with the same request
        // context, so a byte-identical absolute URL here is what makes the subscription match.
        $routes = new RouteCollection();
        $routes->add('api_books_get', new Route('/api/books/{id}'));

        $this->assertSame(
            (new UrlGenerator($routes, $context))->generate('api_books_get', ['id' => 42], UrlGeneratorInterface::ABSOLUTE_URL),
            $resolver->absoluteUrl('/api/books/42'),
        );
    }

    /**
     * @return iterable<string, array{0: RequestContext, 1: string}>
     */
    public static function provideRequestContexts(): iterable
    {
        yield 'https on the default port' => [
            new RequestContext(host: 'api.example.com', scheme: 'https'),
            'https://api.example.com',
        ];

        yield 'http on the default port' => [
            new RequestContext(host: 'api.example.com'),
            'http://api.example.com',
        ];

        yield 'https on a non-default port' => [
            new RequestContext(host: 'api.example.com', scheme: 'https', httpsPort: 8443),
            'https://api.example.com:8443',
        ];

        yield 'http on a non-default port' => [
            new RequestContext(host: 'api.example.com', httpPort: 8080),
            'http://api.example.com:8080',
        ];

        yield 'a front controller base path' => [
            new RequestContext(baseUrl: '/app', host: 'api.example.com', scheme: 'https'),
            'https://api.example.com/app',
        ];
    }

    #[Test]
    public function it_returns_the_path_unchanged_without_a_routing_context(): void
    {
        $this->assertSame('/api/books/{id}', (new MercureTopicUrlResolver())->absoluteUrl('/api/books/{id}'));
    }

    private function routerWithContext(RequestContext $context): RouterInterface
    {
        $router = $this->createStub(RouterInterface::class);
        $router
            ->method('getContext')
            ->willReturn($context);

        return $router;
    }
}
