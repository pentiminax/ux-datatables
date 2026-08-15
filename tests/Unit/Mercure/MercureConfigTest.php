<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MercureConfig::class)]
final class MercureConfigTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('provideSerializations')]
    public function it_serializes_the_configuration(bool $withCredentials, ?int $debounceMs, array $expected): void
    {
        $config = (new MercureConfig(
            topics: ['datatables/MyTable'],
            withCredentials: $withCredentials,
            debounceMs: $debounceMs,
        ))->withHubUrl('/.well-known/mercure');

        $this->assertSame($expected, $config->jsonSerialize());
    }

    /**
     * @return iterable<string, array{bool, ?int, array<string, mixed>}>
     */
    public static function provideSerializations(): iterable
    {
        yield 'optional fields are omitted' => [false, null, [
            'hubUrl' => '/.well-known/mercure',
            'topics' => ['datatables/MyTable'],
        ]];

        yield 'credentials only' => [true, null, [
            'hubUrl'          => '/.well-known/mercure',
            'topics'          => ['datatables/MyTable'],
            'withCredentials' => true,
        ]];

        yield 'debounce only' => [false, 1000, [
            'hubUrl'     => '/.well-known/mercure',
            'topics'     => ['datatables/MyTable'],
            'debounceMs' => 1000,
        ]];

        yield 'all fields' => [true, 300, [
            'hubUrl'          => '/.well-known/mercure',
            'topics'          => ['datatables/MyTable'],
            'withCredentials' => true,
            'debounceMs'      => 300,
        ]];
    }

    #[Test]
    public function it_normalizes_topics(): void
    {
        $config = new MercureConfig(
            topics: ['/api/books/{id}', '', '/api/authors/{id}'],
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
