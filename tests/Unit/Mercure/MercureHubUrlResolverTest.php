<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * @internal
 */
#[CoversClass(MercureHubUrlResolver::class)]
final class MercureHubUrlResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_the_hub_url(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('getPublicUrl')->willReturn('https://example.com/.well-known/mercure');

        $this->assertSame(
            'https://example.com/.well-known/mercure',
            (new MercureHubUrlResolver($hub))->resolveHubUrl()
        );
    }

    #[Test]
    public function it_returns_null_for_an_empty_hub_url(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('getPublicUrl')->willReturn('');

        $this->assertNull((new MercureHubUrlResolver($hub))->resolveHubUrl());
    }

    #[Test]
    public function it_resolves_the_mercure_one_protocol_version_of_the_hub(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('getProtocolVersion')->willReturn(ProtocolVersion::V1);

        $this->assertSame(
            MercureConfig::PROTOCOL_VERSION_1_0,
            (new MercureHubUrlResolver($hub))->resolveProtocolVersion()
        );
    }

    #[Test]
    public function it_resolves_the_legacy_protocol_version_of_the_hub(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('getProtocolVersion')->willReturn(ProtocolVersion::Legacy);

        $this->assertSame(
            MercureConfig::PROTOCOL_VERSION_0_X,
            (new MercureHubUrlResolver($hub))->resolveProtocolVersion()
        );
    }
}
