<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\RowMapper\Stage\ActionResolutionStage;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRowStageContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ActionResolutionStage::class)]
final class ActionResolutionStageTest extends TestCase
{
    use BuildsRowStageContext;

    /**
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('resolvedRowProvider')]
    public function it_resolves_action_data_only_when_an_action_column_is_configured(array $columns, array $expected): void
    {
        $stage = new ActionResolutionStage(new ActionRowDataResolver());

        $result = $stage->process(['id' => 3], ['id' => 3], $columns);

        $this->assertSame($expected, $result);
    }

    public static function resolvedRowProvider(): iterable
    {
        yield 'action column exposes the resolved url' => [
            [
                TextColumn::new('id'),
                self::detailActionColumn(static fn (array $row): string => '/items/'.$row['id']),
            ],
            [
                'id'                                   => 3,
                ActionRowDataResolver::ROW_ACTIONS_KEY => ['DETAIL' => ['url' => '/items/3']],
            ],
        ];

        yield 'row without action column is passed through' => [
            [TextColumn::new('id')],
            ['id' => 3],
        ];
    }
}
