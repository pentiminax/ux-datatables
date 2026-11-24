<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SearchBuilderExtension;
use Pentiminax\UX\DataTables\Model\Summary\Sum;
use Pentiminax\UX\DataTables\Runtime\TableRequirements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TableRequirements::class)]
final class TableRequirementsTest extends TestCase
{
    #[Test]
    #[DataProvider('columnControlSearches')]
    public function it_requires_column_control_when_a_search_is_offered(\Closure $configure): void
    {
        $this->assertTrue(TableRequirements::of($configure(new DataTable('books')))->has(DataSourceFeature::ColumnControl));
    }

    /**
     * @return iterable<string, array{\Closure(DataTable): DataTable}>
     */
    public static function columnControlSearches(): iterable
    {
        yield 'default ColumnControl, which holds a search' => [
            static fn (DataTable $table): DataTable => $table->addExtension(new ColumnControlExtension()),
        ];

        yield 'search nested in a dropdown' => [
            static fn (DataTable $table): DataTable => $table->addExtension((new ColumnControlExtension([]))->add(0, [['extend' => 'dropdown', 'content' => ['searchText']]])),
        ];

        yield 'search set on one column' => [
            static fn (DataTable $table): DataTable => $table
                ->addExtension((new ColumnControlExtension([]))->add(0, ['order']))
                ->columns([TextColumn::new('title')->columnControl(['searchList'])]),
        ];

        yield 'SearchList object' => [
            static fn (DataTable $table): DataTable => $table->addExtension((new ColumnControlExtension([]))->add(1, [SearchList::new()])),
        ];
    }

    #[Test]
    #[DataProvider('columnControlWithoutSearch')]
    public function it_does_not_require_column_control_without_a_search(\Closure $configure): void
    {
        $this->assertFalse(TableRequirements::of($configure(new DataTable('books')))->has(DataSourceFeature::ColumnControl));
    }

    /**
     * @return iterable<string, array{\Closure(DataTable): DataTable}>
     */
    public static function columnControlWithoutSearch(): iterable
    {
        yield 'ordering only' => [
            static fn (DataTable $table): DataTable => $table->addExtension((new ColumnControlExtension([]))->add(0, ['order', ['orderAsc', 'orderDesc']])),
        ];

        yield 'a dropdown whose icon is named search' => [
            static fn (DataTable $table): DataTable => $table->addExtension((new ColumnControlExtension([]))->add(0, [['extend' => 'dropdown', 'icon' => 'search', 'content' => ['orderAsc', 'orderDesc']]])),
        ];
    }

    #[Test]
    public function it_requires_the_query_features_the_table_configures(): void
    {
        $table = (new DataTable('books'))
            ->addExtension(new SearchBuilderExtension())
            ->columns([NumberColumn::new('price')->summarize(new Sum())]);

        $requirements = TableRequirements::of($table);

        $this->assertTrue($requirements->has(DataSourceFeature::SearchBuilder));
        $this->assertTrue($requirements->has(DataSourceFeature::Summaries));
        $this->assertFalse($requirements->has(DataSourceFeature::ConfiguredFilters));
        $this->assertFalse($requirements->has(DataSourceFeature::RowScope));
    }

    #[Test]
    #[DataProvider('actionsServedByTheBundle')]
    public function it_serves_row_actions_and_requires_the_row_scope(\Closure $configure): void
    {
        $requirements = TableRequirements::of($configure(new DataTable('people')));

        $this->assertTrue($requirements->servesRowActions);
        $this->assertTrue($requirements->rendersRowActions);
        $this->assertTrue($requirements->has(DataSourceFeature::RowScope));
    }

    /**
     * @return iterable<string, array{\Closure(DataTable): DataTable}>
     */
    public static function actionsServedByTheBundle(): iterable
    {
        yield 'row action switch' => [static fn (DataTable $table): DataTable => $table->columns([BooleanColumn::new('active')->renderAsSwitch()])];
        yield 'delete' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete()))])];
        yield 'collapsible detail' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::detail()->collapsible('detail.html.twig')))])];
        yield 'delete linked to a route' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete()->linkToUrl('/books')))])];
        yield 'collapsible detail linked to a url' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::detail()->collapsible('detail.html.twig')->linkToUrl('/books')))])];
    }

    #[Test]
    #[DataProvider('actionsServedByNoEndpoint')]
    public function it_does_not_serve_actions_no_endpoint_handles(\Closure $configure, bool $rendersRowActions): void
    {
        $requirements = TableRequirements::of($configure(new DataTable('people')));

        $this->assertFalse($requirements->servesRowActions);
        $this->assertSame($rendersRowActions, $requirements->rendersRowActions);
        $this->assertFalse($requirements->has(DataSourceFeature::RowScope));
    }

    /**
     * @return iterable<string, array{\Closure(DataTable): DataTable, bool}>
     */
    public static function actionsServedByNoEndpoint(): iterable
    {
        yield 'no columns' => [static fn (DataTable $table): DataTable => $table, false];
        yield 'empty actions' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', new Actions())]), true];
        yield 'link-only detail' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::detail()->linkToUrl('/books')))]), true];
        yield 'custom action' => [static fn (DataTable $table): DataTable => $table->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::new('open')->linkToUrl('/books')))]), true];
    }

    #[Test]
    public function it_requires_the_row_scope_for_bulk_actions_alone(): void
    {
        $table = (new DataTable('people'))->setBulkActions((new BulkActions())->add(BulkAction::new('touch')->handler(static fn () => null)));

        $requirements = TableRequirements::of($table);

        $this->assertTrue($requirements->has(DataSourceFeature::RowScope));
        $this->assertFalse($requirements->servesRowActions);
    }

    #[Test]
    public function it_drops_the_row_scope_when_the_table_opts_out_of_it(): void
    {
        $table = (new DataTable('people'))
            ->columns([ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete()))])
            ->unscopedMutations();

        $requirements = TableRequirements::of($table);

        $this->assertFalse($requirements->has(DataSourceFeature::RowScope));
        $this->assertTrue($requirements->servesRowActions);
    }
}
