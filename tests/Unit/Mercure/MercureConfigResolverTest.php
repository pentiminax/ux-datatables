<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Contracts\ApiResourceMercureMetadataResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MercureConfigResolver::class)]
final class MercureConfigResolverTest extends TestCase
{
    #[Test]
    public function it_returns_null_without_hub_url(): void
    {
        $hubResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubResolver
            ->method('resolveHubUrl')
            ->willReturn(null);

        $resolver = new MercureConfigResolver($hubResolver);

        $this->assertNull($resolver->resolveMercureConfig('App\\Entity\\Book'));
    }

    #[Test]
    public function it_uses_api_platform_topics_when_available(): void
    {
        $hubResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubResolver
            ->method('resolveHubUrl')
            ->willReturn('http://localhost/.well-known/mercure');

        $metadataResolver = $this->createMock(ApiResourceMercureMetadataResolverInterface::class);
        $metadataResolver
            ->expects($this->once())
            ->method('resolveTopics')
            ->with('App\\Entity\\Book')
            ->willReturn(['/api/books/{id}', '/api/authors/{id}']);

        $resolver = new MercureConfigResolver($hubResolver, $metadataResolver);
        $config   = $resolver->resolveMercureConfig('App\\Entity\\Book');

        $this->assertSame('http://localhost/.well-known/mercure', $config?->hubUrl);
        $this->assertSame(['/api/books/{id}', '/api/authors/{id}'], $config?->topics);
    }

    #[Test]
    public function it_falls_back_to_internal_topic_when_metadata_is_missing(): void
    {
        $hubResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubResolver
            ->method('resolveHubUrl')
            ->willReturn('http://localhost/.well-known/mercure');

        $metadataResolver = $this->createMock(ApiResourceMercureMetadataResolverInterface::class);
        $metadataResolver
            ->method('resolveTopics')
            ->willReturn([]);

        $resolver = new MercureConfigResolver($hubResolver, $metadataResolver);
        $config   = $resolver->resolveMercureConfig('App\\Entity\\BookCategory');

        $this->assertSame(['/datatables/book-categories/{id}'], $config?->topics);
    }
}
