<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\RowMapper\Stage\NormalizationStage;
use Pentiminax\UX\DataTables\Tests\Unit\Column\TestStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NormalizationStage::class)]
final class NormalizationStageTest extends TestCase
{
    /**
     * @param array<string, mixed>  $mappedRow
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('normalizedRowProvider')]
    public function it_normalizes_object_values_per_column(array $mappedRow, array $columns, array $expected): void
    {
        $result = (new NormalizationStage())->process($mappedRow, 'original', $columns);

        $this->assertSame($expected, $result);
    }

    public static function normalizedRowProvider(): iterable
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Label';
            }
        };

        $company = new class {
            public string $name = 'Acme';
        };

        yield 'scalar value is left unchanged' => [
            ['title' => 'Hello'],
            [TextColumn::new('title')],
            ['title' => 'Hello'],
        ];

        yield 'stringable is converted to string' => [
            ['name' => $stringable],
            [TextColumn::new('name')],
            ['name' => 'Label'],
        ];

        yield 'non stringable object is converted to null' => [
            ['obj' => new \stdClass()],
            [TextColumn::new('obj')],
            ['obj' => null],
        ];

        yield 'backed enum is converted to its value' => [
            ['status' => TestStatus::Active],
            [ChoiceColumn::new('status')],
            ['status' => 'active'],
        ];

        yield 'date column formats datetime' => [
            ['date' => new \DateTimeImmutable('2024-06-01')],
            [DateColumn::new('date')->setFormat('d/m/Y')],
            ['date' => '01/06/2024'],
        ];

        yield 'dotted field path is resolved' => [
            ['company' => $company],
            [TextColumn::new('company')->setField('company.name')],
            ['company' => 'Acme'],
        ];

        yield 'column without key is skipped' => [
            ['title' => 'Hello'],
            [TextColumn::new('')],
            ['title' => 'Hello'],
        ];
    }
}
