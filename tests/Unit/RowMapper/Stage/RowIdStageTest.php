<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Highlight\HighlightConfig;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\RowMapper\Stage\RowIdStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RowIdStage::class)]
final class RowIdStageTest extends TestCase
{
    #[Test]
    #[DataProvider('provideIdentifiers')]
    public function it_exposes_the_row_identifier_as_a_string(mixed $id, ?string $expected): void
    {
        $result = (new RowIdStage())->process(['email' => 'user@example.com'], ['id' => $id], []);

        $this->assertSame($expected, $result[HighlightConfig::ROW_ID_KEY] ?? null);
    }

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function provideIdentifiers(): iterable
    {
        yield 'integer' => [42, '42'];
        yield 'string' => ['01J0', '01J0'];
        yield 'empty string' => ['', null];
        yield 'null' => [null, null];
        yield 'unsupported type' => [[1], null];
    }

    #[Test]
    public function it_reads_the_identifier_from_the_source_item_of_a_row_context(): void
    {
        $context = new RowContext(source: ['id' => 7], item: ['email' => 'user@example.com']);

        $result = (new RowIdStage())->process(['email' => 'user@example.com'], $context, []);

        $this->assertSame('7', $result[HighlightConfig::ROW_ID_KEY]);
    }

    #[Test]
    public function it_honors_a_custom_id_field(): void
    {
        $result = (new RowIdStage('reference'))->process([], ['reference' => 'INV-1'], []);

        $this->assertSame('INV-1', $result[HighlightConfig::ROW_ID_KEY]);
    }

    #[Test]
    public function it_keeps_an_identifier_already_present_on_the_mapped_row(): void
    {
        $result = (new RowIdStage())->process(
            [HighlightConfig::ROW_ID_KEY => 'custom'],
            ['id' => 42],
            [],
        );

        $this->assertSame('custom', $result[HighlightConfig::ROW_ID_KEY]);
    }
}
