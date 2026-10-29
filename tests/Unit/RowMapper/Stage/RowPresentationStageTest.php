<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\RowMapper\Stage\RowPresentationStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RowPresentationStage::class)]
final class RowPresentationStageTest extends TestCase
{
    #[Test]
    #[DataProvider('provideClasses')]
    public function it_emits_only_class_safe_tokens(mixed $returned, ?string $expected): void
    {
        $result = (new RowPresentationStage(rowClass: static fn (): mixed => $returned))->process(['name' => 'A'], ['id' => 1], []);

        $this->assertSame($expected, $result['DT_RowClass'] ?? null);
    }

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function provideClasses(): iterable
    {
        yield 'single' => ['table-danger', 'table-danger'];
        yield 'several' => ["is-late  fw-bold\tdup dup", 'is-late fw-bold dup'];
        yield 'attribute breakout dropped' => ['ok" onclick="alert(1)', null];
        yield 'safe token next to breakout' => ['ok onclick="alert(1)', 'ok'];
        yield 'markup dropped' => ['<script> x', 'x'];
        yield 'leading digit dropped' => ['1bad good', 'good'];
        yield 'only unsafe' => ['"><img', null];
        yield 'empty' => ['', null];
        yield 'null' => [null, null];
        yield 'not a string' => [['a'], null];
    }

    #[Test]
    public function it_emits_the_record_url_and_keeps_existing_row_attributes(): void
    {
        $stage  = new RowPresentationStage(recordUrl: static fn (array $row): string => '/users/'.$row['id']);
        $result = $stage->process(['DT_RowAttr' => ['data-x' => '1']], ['id' => 7], []);

        $this->assertSame(['data-record-url' => '/users/7', 'data-x' => '1'], $result['DT_RowAttr']);
    }

    #[Test]
    #[DataProvider('provideUnsafeUrls')]
    public function it_drops_unsafe_record_urls(?string $url): void
    {
        $result = (new RowPresentationStage(recordUrl: static fn (): ?string => $url))->process(['name' => 'A'], ['id' => 1], []);

        $this->assertSame(['name' => 'A'], $result);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideUnsafeUrls(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'data' => ['data:text/html;base64,PHNjcmlwdD4='];
        yield 'protocol relative' => ['//evil.example'];
        yield 'null' => [null];
    }

    #[Test]
    public function it_hands_the_source_row_to_the_callables(): void
    {
        $stage  = new RowPresentationStage(rowClass: static fn (array $row): string => 'row-'.$row['id']);
        $result = $stage->process([], new RowContext(['id' => 3], ['projected' => true]), []);

        $this->assertSame('row-3', $result['DT_RowClass']);
    }

    #[Test]
    public function it_leaves_the_row_untouched_without_callables(): void
    {
        $this->assertSame(['name' => 'A'], (new RowPresentationStage())->process(['name' => 'A'], ['id' => 1], []));
    }
}
