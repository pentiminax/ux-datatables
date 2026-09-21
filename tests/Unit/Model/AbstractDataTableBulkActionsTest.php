<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Highlight\HighlightConfig;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableBulkActionsTest extends TestCase
{
    #[Test]
    public function it_enables_a_multi_selection_with_checkboxes_when_bulk_actions_exist(): void
    {
        $select = $this->table()->getDataTable()->getExtensionsCollection()->getSelectExtension();

        $this->assertNotNull($select);
        $this->assertSame(SelectStyle::MULTI, $select->getStyle());
        $payload = $select->jsonSerialize();
        $this->assertTrue($payload['headerCheckbox']);
        $this->assertTrue($payload['withCheckbox']);
    }

    #[Test]
    public function it_keeps_a_multi_selection_the_user_already_configured(): void
    {
        $configured = new SelectExtension(style: SelectStyle::MULTI, className: 'chosen');

        $table = $this->table(static fn (DataTable $t): DataTable => $t->extensions([$configured]));

        $this->assertSame($configured, $table->getDataTable()->getExtensionsCollection()->getSelectExtension());
    }

    #[Test]
    public function it_refuses_a_single_row_selection_alongside_bulk_actions(): void
    {
        $this->expectException(\LogicException::class);

        $this->table(static fn (DataTable $t): DataTable => $t->extensions([new SelectExtension(style: SelectStyle::SINGLE)]))
            ->getDataTable();
    }

    #[Test]
    public function it_leaves_the_selection_alone_without_bulk_actions(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);

        $this->assertNull($table->getDataTable()->getExtensionsCollection()->getSelectExtension());
        $this->assertArrayNotHasKey('bulkActions', $table->getDataTable()->getOptions());
    }

    #[Test]
    public function it_places_the_bulk_bar_marker_and_forces_a_row_id_in_the_payload(): void
    {
        $options = $this->table()->getDataTable()->getOptions();

        $this->assertSame(HighlightConfig::ROW_ID_KEY, $options['rowId']);
        $this->assertSame(['approve'], array_column($options['bulkActions']['actions'], 'name'));
        $this->assertFalse($options['bulkActions']['selectCurrentPageOnly']);
        $this->assertContains(Feature::BULK_ACTIONS->value, $this->flatten($options['layout']));
    }

    #[Test]
    public function it_gives_every_client_side_row_an_identifier(): void
    {
        $table = $this->table();
        $table->setData([['id' => 7, 'title' => 'Heat']]);

        $rows = $table->getDataTable()->getOption('data');

        $this->assertSame('7', $rows[0][HighlightConfig::ROW_ID_KEY]);
    }

    private function table(?\Closure $configureTable = null): ConfigurableDataTable
    {
        return new ConfigurableDataTable(
            [TextColumn::new('id'), TextColumn::new('title')],
            configureTable: $configureTable,
            bulkActions: static fn (BulkActions $actions): BulkActions => $actions->add(BulkAction::new('approve')),
        );
    }

    /**
     * @param array<string, mixed> $layout
     *
     * @return list<string>
     */
    private function flatten(array $layout): array
    {
        $values = [];

        array_walk_recursive($layout, static function (mixed $value) use (&$values): void {
            if (\is_string($value)) {
                $values[] = $value;
            }
        });

        return $values;
    }
}
