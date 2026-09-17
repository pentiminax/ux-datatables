<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
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

    #[Test]
    public function it_subscribes_with_credentials_for_a_private_resource(): void
    {
        $metadataResolver = $this->createStub(ApiResourceMercureMetadataResolver::class);
        $metadataResolver
            ->method('resolveTopics')
            ->willReturn(['/api/books/{id}']);
        $metadataResolver
            ->method('resolvePrivate')
            ->willReturn(true);

        $resolver = new MercureConfigResolver(
            $this->hubUrlResolver('https://example.com/.well-known/mercure'),
            $metadataResolver,
        );
        $config = $resolver->resolveMercureConfig('App\\Entity\\Book');

        $this->assertTrue($config?->withCredentials);
        $this->assertSame([
            'hubUrl'          => 'https://example.com/.well-known/mercure',
            'topics'          => ['/api/books/{id}'],
            'withCredentials' => true,
        ], $config?->jsonSerialize());
    }

    #[Test]
    public function it_serializes_the_unchanged_payload_for_a_resource_without_private_metadata(): void
    {
        $metadataResolver = $this->createStub(ApiResourceMercureMetadataResolver::class);
        $metadataResolver
            ->method('resolveTopics')
            ->willReturn(['/api/books/{id}']);
        $metadataResolver
            ->method('resolvePrivate')
            ->willReturn(false);

        $resolver = new MercureConfigResolver(
            $this->hubUrlResolver('https://example.com/.well-known/mercure'),
            $metadataResolver,
        );
        $config = $resolver->resolveMercureConfig('App\\Entity\\Book');

        $this->assertFalse($config?->withCredentials);
        $this->assertSame([
            'hubUrl' => 'https://example.com/.well-known/mercure',
            'topics' => ['/api/books/{id}'],
        ], $config?->jsonSerialize());
    }

    #[Test]
    public function it_carries_the_hub_protocol_version(): void
    {
        $metadataResolver = $this->createStub(ApiResourceMercureMetadataResolver::class);
        $metadataResolver
            ->method('resolveTopics')
            ->willReturn(['/api/books/{id}']);

        $resolver = new MercureConfigResolver($this->hubUrlResolver('http://localhost/.well-known/mercure', '1.0'), $metadataResolver);
        $config   = $resolver->resolveMercureConfig('App\Entity\Book');

        $this->assertSame(MercureConfig::PROTOCOL_VERSION_1_0, $config?->protocolVersion);
    }

    private function hubUrlResolver(?string $hubUrl, string $protocolVersion = MercureConfig::PROTOCOL_VERSION_0_X): MercureHubUrlResolver
    {
        $hubResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubResolver
            ->method('resolveHubUrl')
            ->willReturn($hubUrl);
        $hubResolver
            ->method('resolveProtocolVersion')
            ->willReturn($protocolVersion);

        return $hubResolver;
    }
}
