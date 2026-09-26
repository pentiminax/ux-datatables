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
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
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
    public function it_replaces_a_bundle_wide_single_selection_instead_of_refusing_it(): void
    {
        $table = $this->table();
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(extensions: ['select' => ['style' => 'single']]));

        $dataTable = $table->getDataTable();
        $payload   = $dataTable->getExtensionsCollection()->getSelectExtension()?->jsonSerialize();

        $this->assertSame('multi', $payload['style'] ?? null);
        $this->assertTrue($payload['withCheckbox']);
        $this->assertTrue($payload['headerCheckbox']);
        $this->assertTrue($dataTable->isSelectionForBulkActions());
    }

    #[Test]
    public function it_adds_checkboxes_to_a_bundle_wide_multi_selection(): void
    {
        $table = $this->table();
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(extensions: ['select' => ['style' => 'multi']]));

        $payload = $table->getDataTable()->getExtensionsCollection()->getSelectExtension()?->jsonSerialize();

        $this->assertTrue($payload['withCheckbox'] ?? null);
    }

    #[Test]
    public function it_refuses_a_bundle_wide_selection_the_table_switched_to_single_in_place(): void
    {
        $table = $this->table(static function (DataTable $t): DataTable {
            $t->getExtensionsCollection()->getSelectExtension()?->style(SelectStyle::SINGLE);

            return $t;
        });
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(extensions: ['select' => ['style' => 'multi']]));

        $this->expectException(\LogicException::class);

        $table->getDataTable();
    }

    #[Test]
    public function it_keeps_a_bundle_wide_selection_the_table_customized_in_place(): void
    {
        $table = $this->table(static function (DataTable $t): DataTable {
            $t->getExtensionsCollection()->getSelectExtension()?->withCheckbox();

            return $t;
        });
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(extensions: ['select' => ['style' => 'multi']]));

        $dataTable = $table->getDataTable();
        $payload   = $dataTable->getExtensionsCollection()->getSelectExtension()?->jsonSerialize();

        $this->assertTrue($payload['withCheckbox'] ?? null);
        $this->assertFalse($payload['headerCheckbox']);
        $this->assertFalse($dataTable->isSelectionForBulkActions());
    }

    #[Test]
    public function it_does_not_claim_a_selection_the_table_declared(): void
    {
        $table = $this->table(static fn (DataTable $t): DataTable => $t->extensions([new SelectExtension(style: SelectStyle::MULTI)]));

        $this->assertFalse($table->getDataTable()->isSelectionForBulkActions());
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
        $this->assertSame('topEnd', $options['bulkActions']['position']);
        $this->assertContains(Feature::BULK_ACTIONS->value, $this->flatten((array) $options['layout']['topEnd']));
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
     * @param array<mixed> $layout
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
