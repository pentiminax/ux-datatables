<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Highlight;

use Pentiminax\UX\DataTables\Highlight\HighlightConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HighlightConfig::class)]
final class HighlightConfigTest extends TestCase
{
    /**
     * @param string[]             $ignoreColumns
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('provideSerializations')]
    public function it_serializes_the_configuration(int $durationMs, array $ignoreColumns, array $expected): void
    {
        $config = new HighlightConfig(durationMs: $durationMs, ignoreColumns: $ignoreColumns);

        $this->assertSame($expected, $config->jsonSerialize());
    }

    /**
     * @return iterable<string, array{int, string[], array<string, mixed>}>
     */
    public static function provideSerializations(): iterable
    {
        yield 'duration only' => [1200, [], ['durationMs' => 1200]];

        yield 'ignored columns' => [800, ['lastLoginAt'], [
            'durationMs'    => 800,
            'ignoreColumns' => ['lastLoginAt'],
        ]];
    }

    #[Test]
    public function it_serializes_a_custom_id_field_for_the_client(): void
    {
        $config = new HighlightConfig(idField: 'uuid');

        $this->assertSame(['durationMs' => 1200, 'idField' => 'uuid'], $config->jsonSerialize());
    }

    #[Test]
    public function it_omits_the_default_id_field(): void
    {
        $this->assertArrayNotHasKey('idField', (new HighlightConfig())->jsonSerialize());
    }

    #[Test]
    public function it_discards_empty_ignored_columns(): void
    {
        $config = new HighlightConfig(ignoreColumns: ['lastLoginAt', '', 'updatedAt']);

        $this->assertSame(['lastLoginAt', 'updatedAt'], $config->ignoreColumns);
    }

    #[Test]
    public function it_rejects_a_non_positive_duration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Highlight duration must be a positive number of milliseconds.');

        new HighlightConfig(durationMs: 0);
    }

    #[Test]
    public function it_rejects_an_empty_id_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Highlight id field cannot be empty.');

        new HighlightConfig(idField: '');
    }
}
