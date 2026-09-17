<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;
use Pentiminax\UX\DataTables\Mercure\MercureTopicUrlResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

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

        yield 'a custom variable-bearing operation before the item GET does not win' => [
            (new ApiResource())->withOperations(new Operations([
                new Post(uriTemplate: '/books/{id}/publish{._format}', routePrefix: '/api'),
                new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api'),
            ])),
            ['/api/books/{id}'],
        ];

        yield 'a resource without an item GET has no item topic, as API Platform has no item IRI' => [
            (new ApiResource())->withOperations(new Operations([
                new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
                new Post(uriTemplate: '/books/{id}/publish{._format}', routePrefix: '/api'),
            ])),
            [],
        ];

        yield 'a custom GET HttpOperation is an item operation too' => [
            (new ApiResource())->withOperations(new Operations([
                new HttpOperation(method: 'GET', uriTemplate: '/books/{id}/preview{._format}', routePrefix: '/api'),
                new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api'),
            ])),
            ['/api/books/{id}/preview'],
        ];

        yield 'a declared topic on a later operation beats the item path' => [
            (new ApiResource())->withOperations(new Operations([
                new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api'),
                new Post(
                    uriTemplate: '/books{._format}',
                    routePrefix: '/api',
                    mercure: ['topics' => ['https://example.com/custom']],
                ),
            ])),
            ['https://example.com/custom'],
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

    #[Test]
    #[DataProvider('providePrivateMetadata')]
    public function it_resolves_private_from_resource_then_operation(ApiResource $resource, bool $expected): void
    {
        $this->assertSame($expected, $this->resolver($resource)->resolvePrivate(self::ENTITY_CLASS));
    }

    /**
     * @return iterable<string, array{0: ApiResource, 1: bool}>
     */
    public static function providePrivateMetadata(): iterable
    {
        yield 'resource level private' => [
            (new ApiResource(mercure: ['private' => true]))
                ->withOperations(new Operations([
                    new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api'),
                ])),
            true,
        ];

        yield 'operation level private' => [
            (new ApiResource())->withOperations(new Operations([
                new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api', mercure: ['private' => true]),
            ])),
            true,
        ];

        yield 'operation level private wins over a non-private resource' => [
            (new ApiResource(mercure: ['private' => false]))->withOperations(new Operations([
                new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api', mercure: ['private' => true]),
            ])),
            true,
        ];

        yield 'resource level private is not overridden by operation topics' => [
            (new ApiResource(mercure: ['private' => true]))->withOperations(new Operations([
                new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api', mercure: ['topics' => ['/api/books/{id}']]),
            ])),
            true,
        ];

        yield 'private explicitly false' => [
            new ApiResource(mercure: ['private' => false]),
            false,
        ];

        yield 'private as a string is not a flag' => [
            new ApiResource(mercure: ['private' => 'yes']),
            false,
        ];

        yield 'no mercure metadata' => [
            new ApiResource(),
            false,
        ];
    }

    #[Test]
    public function it_reports_a_non_private_resource_when_the_metadata_factory_throws(): void
    {
        $factory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->willThrowException(new \RuntimeException('boom'));

        $resolver = new ApiResourceMercureMetadataResolver($factory);

        $this->assertFalse($resolver->resolvePrivate(self::ENTITY_CLASS));
    }

    #[Test]
    public function it_builds_the_item_topic_absolutely_on_a_same_host_hub(): void
    {
        $resource = (new ApiResource())->withOperations(new Operations([
            new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
            new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api'),
        ]));

        $this->assertSame(
            'https://api.example.com/api/books/{id}',
            $this->resolver($resource, absolute: true)->resolveTopics(self::ENTITY_CLASS)[0],
        );
    }

    #[Test]
    public function it_resolves_the_iri_object_idiom_to_the_absolute_item_topic(): void
    {
        $resource = (new ApiResource(mercure: ['topics' => ['@=iri(object)']]))
            ->withOperations(new Operations([
                new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
                new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api'),
            ]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->assertSame(
            ['https://api.example.com/api/books/{id}'],
            $this->resolver($resource, absolute: true, logger: $logger)->resolveTopics(self::ENTITY_CLASS),
        );
    }

    #[Test]
    public function it_resolves_the_iri_object_idiom_next_to_a_plain_topic(): void
    {
        $resource = (new ApiResource())->withOperations(new Operations([
            new Get(
                uriTemplate: '/books/{id}{._format}',
                routePrefix: '/api',
                mercure: ['topics' => ['https://example.com/books', '@=iri(object)']],
            ),
        ]));

        $this->assertSame(
            ['https://example.com/books', 'https://api.example.com/api/books/{id}'],
            $this->resolver($resource, absolute: true)->resolveTopics(self::ENTITY_CLASS),
        );
    }

    #[Test]
    public function it_logs_a_warning_when_an_expression_topic_is_dropped(): void
    {
        $resource = (new ApiResource())->withOperations(new Operations([
            new Get(
                uriTemplate: '/books/{id}{._format}',
                routePrefix: '/api',
                mercure: ['topics' => ['@=object.getMercureTopic()']],
            ),
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('@=object.getMercureTopic()'));

        $this->assertSame(
            ['https://api.example.com/api/books/{id}'],
            $this->resolver($resource, absolute: true, logger: $logger)->resolveTopics(self::ENTITY_CLASS),
        );
    }

    #[Test]
    public function it_takes_the_first_item_get_even_when_its_template_carries_no_variable(): void
    {
        $resource = (new ApiResource())->withOperations(new Operations([
            new Get(uriTemplate: '/books{._format}', routePrefix: '/api'),
            new Get(uriTemplate: '/books/{id}{._format}', routePrefix: '/api'),
        ]));

        // Not the prettier topic, but the one API Platform publishes to.
        $this->assertSame(
            ['https://api.example.com/api/books'],
            $this->resolver($resource, absolute: true)->resolveTopics(self::ENTITY_CLASS),
        );
    }

    #[Test]
    public function it_falls_back_to_the_collection_shaped_operation_when_no_template_carries_a_variable(): void
    {
        $resource = (new ApiResource())->withOperations(new Operations([
            new Get(uriTemplate: '/books{._format}', routePrefix: '/api'),
        ]));

        $this->assertSame(
            ['https://api.example.com/api/books'],
            $this->resolver($resource, absolute: true)->resolveTopics(self::ENTITY_CLASS),
        );
    }

    private function resolver(
        ApiResource $resource,
        bool $absolute = false,
        ?LoggerInterface $logger = null,
    ): ApiResourceMercureMetadataResolver {
        return new ApiResourceMercureMetadataResolver(
            $this->factoryReturning($resource),
            $absolute ? $this->absoluteTopicUrlResolver() : null,
            $logger,
        );
    }

    private function factoryReturning(ApiResource $resource): ResourceMetadataCollectionFactoryInterface
    {
        $factory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $factory
            ->method('create')
            ->with(self::ENTITY_CLASS)
            ->willReturn(new ResourceMetadataCollection(self::ENTITY_CLASS, [$resource]));

        return $factory;
    }

    private function absoluteTopicUrlResolver(): MercureTopicUrlResolver
    {
        $router = $this->createStub(RouterInterface::class);
        $router
            ->method('getContext')
            ->willReturn(new RequestContext(host: 'api.example.com', scheme: 'https'));

        return new MercureTopicUrlResolver($router);
    }
}
