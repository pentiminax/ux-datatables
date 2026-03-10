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
        $config = new MercureConfig(
            hubUrl: '/.well-known/mercure',
            topics: ['datatables/MyTable'],
        );

        $this->assertSame([
            'hubUrl' => '/.well-known/mercure',
            'topics' => ['datatables/MyTable'],
        ], $config->jsonSerialize());
    }

    #[Test]
    public function it_omits_false_with_credentials(): void
    {
        $config = new MercureConfig(
            hubUrl: '/.well-known/mercure',
            topics: ['datatables/MyTable'],
            withCredentials: false,
        );

        $serialized = $config->jsonSerialize();

        $this->assertArrayNotHasKey('withCredentials', $serialized);
    }

    #[Test]
    public function it_includes_with_credentials_when_true(): void
    {
        $config = new MercureConfig(
            hubUrl: '/.well-known/mercure',
            topics: ['datatables/MyTable'],
            withCredentials: true,
        );

        $serialized = $config->jsonSerialize();

        $this->assertTrue($serialized['withCredentials']);
    }

    #[Test]
    public function it_omits_null_debounce(): void
    {
        $config = new MercureConfig(
            hubUrl: '/.well-known/mercure',
            topics: ['datatables/MyTable'],
        );

        $this->assertArrayNotHasKey('debounceMs', $config->jsonSerialize());
    }

    #[Test]
    public function it_includes_debounce_when_set(): void
    {
        $config = new MercureConfig(
            hubUrl: '/.well-known/mercure',
            topics: ['datatables/MyTable'],
            debounceMs: 1000,
        );

        $this->assertSame(1000, $config->jsonSerialize()['debounceMs']);
    }

    #[Test]
    public function it_serializes_all_fields(): void
    {
        $config = new MercureConfig(
            hubUrl: '/.well-known/mercure',
            topics: ['datatables/MyTable'],
            withCredentials: true,
            debounceMs: 300,
        );

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
            hubUrl: '/.well-known/mercure',
            topics: ['/api/books/{id}', '/api/authors/{id}'],
        );

        $this->assertSame(['/api/books/{id}', '/api/authors/{id}'], $config->topics);
    }
}
