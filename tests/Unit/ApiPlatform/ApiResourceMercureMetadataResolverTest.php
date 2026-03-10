<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ApiResourceMercureMetadataResolver::class)]
final class ApiResourceMercureMetadataResolverTest extends TestCase
{
    #[Test]
    public function it_uses_explicit_resource_topics_when_available(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with('App\Entity\Book')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource(mercure: ['topics' => ['/api/books/{id}', '/api/authors/{id}']]))
                    ->withOperations(new Operations([
                        new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
                    ])),
            ]));

        $resolver = new ApiResourceMercureMetadataResolver($factory);

        $this->assertSame(['/api/books/{id}', '/api/authors/{id}'], $resolver->resolveTopics('App\Entity\Book'));
    }

    #[Test]
    public function it_builds_an_item_topic_from_operations(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with('App\Entity\Book')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource())->withOperations(new Operations([
                    new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
                    new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api'),
                ])),
            ]));

        $resolver = new ApiResourceMercureMetadataResolver($factory);

        $this->assertSame(['/api/books/{id}'], $resolver->resolveTopics('App\Entity\Book'));
    }

    #[Test]
    public function it_ignores_expression_topics_and_falls_back_to_item_path(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with('App\Entity\Book')
            ->willReturn(new ResourceMetadataCollection('App\Entity\Book', [
                (new ApiResource())->withOperations(new Operations([
                    new Get(
                        uriTemplate: '/books/{slug}{._format}',
                        routePrefix: '/api',
                        mercure: ['topics' => ['@=object.getMercureTopic()']]
                    ),
                ])),
            ]));

        $resolver = new ApiResourceMercureMetadataResolver($factory);

        $this->assertSame(['/api/books/{slug}'], $resolver->resolveTopics('App\Entity\Book'));
    }

    #[Test]
    public function it_returns_an_empty_list_when_metadata_factory_throws(): void
    {
        $factory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->willThrowException(new \RuntimeException('boom'));

        $resolver = new ApiResourceMercureMetadataResolver($factory);

        $this->assertSame([], $resolver->resolveTopics('App\Entity\Book'));
    }
}
