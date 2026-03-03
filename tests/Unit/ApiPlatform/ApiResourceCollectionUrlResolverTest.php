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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ApiResourceCollectionUrlResolver::class)]
final class ApiResourceCollectionUrlResolverTest extends TestCase
{
    #[Test]
    public function it_builds_path_from_route_prefix_and_uri_template(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with('App\Entity\Book')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource())->withOperations(new Operations([
                    new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
                ])),
            ]));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertSame('/api/books', $resolver->resolveCollectionUrl('App\Entity\Book'));
    }

    #[Test]
    public function it_works_when_route_prefix_is_null(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with('App\Entity\Book')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource())->withOperations(new Operations([
                    new GetCollection(uriTemplate: '/books{._format}'),
                ])),
            ]));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertSame('/api/books', $resolver->resolveCollectionUrl('App\Entity\Book'));
    }

    #[Test]
    public function it_falls_back_to_resource_route_prefix(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with('App\Entity\Book')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource(routePrefix: '/api'))->withOperations(new Operations([
                    new GetCollection(uriTemplate: '/books{._format}'),
                ])),
            ]));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertSame('/api/books', $resolver->resolveCollectionUrl('App\Entity\Book'));
    }

    #[Test]
    public function it_strips_dot_format_suffix(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with('App\Entity\Book')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource())->withOperations(new Operations([
                    new GetCollection(uriTemplate: '/books.{_format}', routePrefix: '/api'),
                ])),
            ]));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertSame('/api/books', $resolver->resolveCollectionUrl('App\Entity\Book'));
    }

    #[Test]
    public function it_returns_null_when_no_get_collection_operation(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource())->withOperations(new Operations([
                    new Get(uriTemplate: '/api/books/{id}'),
                ])),
            ]));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertNull($resolver->resolveCollectionUrl('App\Entity\Book'));
    }

    #[Test]
    public function it_returns_null_when_path_contains_variables(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource())->withOperations(new Operations([
                    new GetCollection(uriTemplate: '/books/{id}{._format}'),
                ])),
            ]));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertNull($resolver->resolveCollectionUrl('App\Entity\Book'));
    }

    #[Test]
    public function it_keeps_path_without_format_suffix(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with('App\Entity\Book')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource())->withOperations(new Operations([
                    new GetCollection(uriTemplate: '/books'),
                ])),
            ]));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertSame('/api/books', $resolver->resolveCollectionUrl('App\Entity\Book'));
    }

    #[Test]
    public function it_returns_null_when_metadata_factory_throws(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->willThrowException(new \RuntimeException('boom'));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertNull($resolver->resolveCollectionUrl('App\Entity\Book'));
    }
}
