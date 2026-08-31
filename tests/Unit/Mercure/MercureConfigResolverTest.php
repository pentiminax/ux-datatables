<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
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
        $resolver = new MercureConfigResolver($this->hubUrlResolver(null));

        $this->assertNull($resolver->resolveMercureConfig('App\\Entity\\Book'));
    }

    #[Test]
    public function it_uses_api_platform_topics_when_available(): void
    {
        $metadataResolver = $this->createMock(ApiResourceMercureMetadataResolver::class);
        $metadataResolver
            ->expects($this->once())
            ->method('resolveTopics')
            ->with('App\\Entity\\Book')
            ->willReturn(['/api/books/{id}', '/api/authors/{id}']);

        $resolver = new MercureConfigResolver($this->hubUrlResolver('http://localhost/.well-known/mercure'), $metadataResolver);
        $config   = $resolver->resolveMercureConfig('App\\Entity\\Book');

        $this->assertSame('http://localhost/.well-known/mercure', $config?->hubUrl);
        $this->assertSame(['/api/books/{id}', '/api/authors/{id}'], $config?->topics);
    }

    #[Test]
    public function it_falls_back_to_internal_topic_when_metadata_is_missing(): void
    {
        $metadataResolver = $this->createStub(ApiResourceMercureMetadataResolver::class);
        $metadataResolver
            ->method('resolveTopics')
            ->willReturn([]);

        $resolver = new MercureConfigResolver($this->hubUrlResolver('http://localhost/.well-known/mercure'), $metadataResolver);
        $config   = $resolver->resolveMercureConfig('App\\Entity\\BookCategory');

        $this->assertSame(['/datatables/book-categories/{id}'], $config?->topics);
    }

    private function hubUrlResolver(?string $hubUrl): MercureHubUrlResolver
    {
        $hubResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubResolver
            ->method('resolveHubUrl')
            ->willReturn($hubUrl);

        return $hubResolver;
    }
}
