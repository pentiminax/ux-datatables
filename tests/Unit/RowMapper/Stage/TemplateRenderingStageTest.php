<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\RowMapper\Stage\TemplateRenderingStage;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRowStageContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TemplateRenderingStage::class)]
final class TemplateRenderingStageTest extends TestCase
{
    use BuildsRowStageContext;

    /**
     * @param array<string, mixed>  $mappedRow
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('renderedRowProvider')]
    public function it_renders_template_columns_and_leaves_the_other_columns_alone(array $mappedRow, array $columns, array $expected): void
    {
        $stage = self::templateRenderingStage(['badge.html.twig' => '<b>{{ data }}</b>']);

        $result = $stage->process($mappedRow, new \stdClass(), $columns);

        $this->assertSame($expected, $result);
    }

    public static function renderedRowProvider(): iterable
    {
        yield 'template column is rendered next to its source value' => [
            ['status' => 'active'],
            [TemplateColumn::new('status_display')->setField('status')->setTemplate('badge.html.twig')],
            ['status' => 'active', 'status_display' => '<b>active</b>'],
        ];

        yield 'non template column is passed through' => [
            ['id' => 42],
            [TextColumn::new('id')],
            ['id' => 42],
        ];
    }
}
