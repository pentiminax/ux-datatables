<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use PHPUnit\Framework\TestCase;

class ApiResourceCollectionUrlResolverTest extends TestCase
{
    public function testResolveCollectionUrlBuildsPathFromRoutePrefixAndUriTemplate(): void
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

    public function testResolveCollectionUrlWorksWhenRoutePrefixIsNull(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with('App\Entity\Book')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource())->withOperations(new Operations([
                    new GetCollection(uriTemplate: '/api/books{._format}'),
                ])),
            ]));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertSame('/api/books', $resolver->resolveCollectionUrl('App\Entity\Book'));
    }

    public function testResolveCollectionUrlFallsBackToResourceRoutePrefix(): void
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

    public function testResolveCollectionUrlStripsDotFormatSuffix(): void
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

    public function testResolveCollectionUrlReturnsNullWhenNoGetCollectionOperationExists(): void
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

    public function testResolveCollectionUrlReturnsNullWhenPathContainsVariablesAfterFormatCleanup(): void
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

    public function testResolveCollectionUrlKeepsPathWithoutFormatSuffix(): void
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

        $this->assertSame('/books', $resolver->resolveCollectionUrl('App\Entity\Book'));
    }

    public function testResolveCollectionUrlReturnsNullWhenMetadataFactoryThrows(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->willThrowException(new \RuntimeException('boom'));

        $resolver = new ApiResourceCollectionUrlResolver($factory);

        $this->assertNull($resolver->resolveCollectionUrl('App\Entity\Book'));
    }
}
