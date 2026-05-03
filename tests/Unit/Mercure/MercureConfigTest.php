<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MercureConfig::class)]
final class MercureConfigTest extends TestCase
{
    #[Test]
    public function it_serializes_required_fields(): void
    {
        $config = (new MercureConfig(topics: ['datatables/MyTable']))
            ->withHubUrl('/.well-known/mercure');

        $this->assertSame([
            'hubUrl' => '/.well-known/mercure',
            'topics' => ['datatables/MyTable'],
        ], $config->jsonSerialize());
    }

    #[Test]
    public function it_omits_false_with_credentials(): void
    {
        $config = (new MercureConfig(
            topics: ['datatables/MyTable'],
            withCredentials: false,
        ))->withHubUrl('/.well-known/mercure');

        $serialized = $config->jsonSerialize();

        $this->assertArrayNotHasKey('withCredentials', $serialized);
    }

    #[Test]
    public function it_includes_with_credentials_when_true(): void
    {
        $config = (new MercureConfig(
            topics: ['datatables/MyTable'],
            withCredentials: true,
        ))->withHubUrl('/.well-known/mercure');

        $serialized = $config->jsonSerialize();

        $this->assertTrue($serialized['withCredentials']);
    }

    #[Test]
    public function it_omits_null_debounce(): void
    {
        $config = (new MercureConfig(topics: ['datatables/MyTable']))
            ->withHubUrl('/.well-known/mercure');

        $this->assertArrayNotHasKey('debounceMs', $config->jsonSerialize());
    }

    #[Test]
    public function it_includes_debounce_when_set(): void
    {
        $config = (new MercureConfig(
            topics: ['datatables/MyTable'],
            debounceMs: 1000,
        ))->withHubUrl('/.well-known/mercure');

        $this->assertSame(1000, $config->jsonSerialize()['debounceMs']);
    }

    #[Test]
    public function it_serializes_all_fields(): void
    {
        $config = (new MercureConfig(
            topics: ['datatables/MyTable'],
            withCredentials: true,
            debounceMs: 300,
        ))->withHubUrl('/.well-known/mercure');

        $this->assertSame([
            'hubUrl'          => '/.well-known/mercure',
            'topics'          => ['datatables/MyTable'],
            'withCredentials' => true,
            'debounceMs'      => 300,
        ], $config->jsonSerialize());
    }

    #[Test]
    public function it_normalizes_multiple_topics(): void
    {
        $config = new MercureConfig(
            topics: ['/api/books/{id}', '/api/authors/{id}'],
        );

        $this->assertSame(['/api/books/{id}', '/api/authors/{id}'], $config->topics);
    }

    #[Test]
    public function it_throws_when_serializing_without_hub_url(): void
    {
        $config = new MercureConfig(topics: ['datatables/MyTable']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('hubUrl is not set');

        $config->jsonSerialize();
    }

    #[Test]
    public function with_hub_url_returns_clone_preserving_other_fields(): void
    {
        $base = new MercureConfig(
            topics: ['datatables/MyTable'],
            withCredentials: true,
            debounceMs: 250,
        );

        $resolved = $base->withHubUrl('/.well-known/mercure');

        $this->assertNull($base->hubUrl);
        $this->assertSame('/.well-known/mercure', $resolved->hubUrl);
        $this->assertSame($base->topics, $resolved->topics);
        $this->assertSame($base->withCredentials, $resolved->withCredentials);
        $this->assertSame($base->debounceMs, $resolved->debounceMs);
    }
}
