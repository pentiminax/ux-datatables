<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ApiResourceCollectionUrlResolver::class)]
final class ApiResourceCollectionUrlResolverTest extends TestCase
{
    private const string ENTITY_CLASS = 'App\Entity\Book';

    #[Test]
    #[DataProvider('provideCollectionOperations')]
    public function it_builds_the_collection_url(ApiResource $resource, string $expectedUrl): void
    {
        $this->assertSame($expectedUrl, $this->resolver($resource)->resolveCollectionUrl(self::ENTITY_CLASS));
    }

    /**
     * @return iterable<string, array{0: ApiResource, 1: string}>
     */
    public static function provideCollectionOperations(): iterable
    {
        yield 'operation route prefix' => [
            (new ApiResource())->withOperations(new Operations([
                new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
            ])),
            '/api/books',
        ];

        yield 'default route prefix when none is declared' => [
            (new ApiResource())->withOperations(new Operations([
                new GetCollection(uriTemplate: '/books{._format}'),
            ])),
            '/api/books',
        ];

        yield 'resource route prefix' => [
            (new ApiResource(routePrefix: '/api'))->withOperations(new Operations([
                new GetCollection(uriTemplate: '/books{._format}'),
            ])),
            '/api/books',
        ];

        yield 'dot format suffix stripped' => [
            (new ApiResource())->withOperations(new Operations([
                new GetCollection(uriTemplate: '/books.{_format}', routePrefix: '/api'),
            ])),
            '/api/books',
        ];

        yield 'uri template without format suffix' => [
            (new ApiResource())->withOperations(new Operations([
                new GetCollection(uriTemplate: '/books'),
            ])),
            '/api/books',
        ];
    }

    #[Test]
    public function it_excludes_operations_that_are_not_collection_operations(): void
    {
        $resource = (new ApiResource())->withOperations(new Operations([
            new Get(uriTemplate: '/api/books/{id}'),
        ]));

        $this->assertNull($this->resolver($resource)->resolveCollectionUrl(self::ENTITY_CLASS));
    }

    #[Test]
    public function it_excludes_paths_that_still_contain_variables(): void
    {
        $resource = (new ApiResource())->withOperations(new Operations([
            new GetCollection(uriTemplate: '/books/{id}{._format}'),
        ]));

        $this->assertNull($this->resolver($resource)->resolveCollectionUrl(self::ENTITY_CLASS));
    }

    #[Test]
    public function it_returns_null_when_metadata_factory_throws(): void
    {
        $factory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->willThrowException(new \RuntimeException('boom'));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertNull($resolver->resolveCollectionUrl(self::ENTITY_CLASS));
    }

    private function resolver(ApiResource $resource): ApiResourceCollectionUrlResolver
    {
        $factory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with(self::ENTITY_CLASS)
            ->willReturn(new ResourceMetadataCollection(self::ENTITY_CLASS, [$resource]));

        return new ApiResourceCollectionUrlResolver($factory);
    }
}
