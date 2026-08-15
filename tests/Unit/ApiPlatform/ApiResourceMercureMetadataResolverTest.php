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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ApiResourceMercureMetadataResolver::class)]
final class ApiResourceMercureMetadataResolverTest extends TestCase
{
    private const string ENTITY_CLASS = 'App\Entity\Book';

    /**
     * @param string[] $expectedTopics
     */
    #[Test]
    #[DataProvider('provideResources')]
    public function it_resolves_topics(ApiResource $resource, array $expectedTopics): void
    {
        $this->assertSame($expectedTopics, $this->resolver($resource)->resolveTopics(self::ENTITY_CLASS));
    }

    /**
     * @return iterable<string, array{0: ApiResource, 1: string[]}>
     */
    public static function provideResources(): iterable
    {
        yield 'explicit resource topics' => [
            (new ApiResource(mercure: ['topics' => ['/api/books/{id}', '/api/authors/{id}']]))
                ->withOperations(new Operations([
                    new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
                ])),
            ['/api/books/{id}', '/api/authors/{id}'],
        ];

        yield 'item topic built from operations' => [
            (new ApiResource())->withOperations(new Operations([
                new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
                new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api'),
            ])),
            ['/api/books/{id}'],
        ];
    }

    #[Test]
    public function it_excludes_expression_topics_and_falls_back_to_the_item_path(): void
    {
        $resource = (new ApiResource())->withOperations(new Operations([
            new Get(
                uriTemplate: '/books/{slug}{._format}',
                routePrefix: '/api',
                mercure: ['topics' => ['@=object.getMercureTopic()']]
            ),
        ]));

        $this->assertSame(['/api/books/{slug}'], $this->resolver($resource)->resolveTopics(self::ENTITY_CLASS));
    }

    #[Test]
    public function it_returns_an_empty_list_when_metadata_factory_throws(): void
    {
        $factory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->willThrowException(new \RuntimeException('boom'));

        $resolver = new ApiResourceMercureMetadataResolver($factory);

        $this->assertSame([], $resolver->resolveTopics(self::ENTITY_CLASS));
    }

    private function resolver(ApiResource $resource): ApiResourceMercureMetadataResolver
    {
        $factory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with(self::ENTITY_CLASS)
            ->willReturn(new ResourceMetadataCollection(self::ENTITY_CLASS, [$resource]));

        return new ApiResourceMercureMetadataResolver($factory);
    }
}
