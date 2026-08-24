<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableActionsTest extends TestCase
{
    /**
     * The single text column of each fixture leaves the actions column at index 1
     * when appended, and at index 0 when prepended.
     */
    #[Test]
    #[DataProvider('actionsColumnPositionProvider')]
    public function it_places_the_actions_column_at_the_configured_position(AbstractDataTable $table, int $index): void
    {
        $columns = $this->columnsOf($table);

        $this->assertInstanceOf(ActionColumn::class, $columns[$index]);
    }

    /**
     * @return iterable<string, array{AbstractDataTable, int}>
     */
    public static function actionsColumnPositionProvider(): iterable
    {
        yield 'after the columns' => [
            self::tableWith(static fn (Actions $actions): Actions => $actions->add(Action::detail())),
            1,
        ];

        yield 'before the columns' => [self::beforeColumnsTable(), 0];
    }

    #[Test]
    #[DataProvider('centeredActionsColumnProvider')]
    public function it_applies_the_actions_column_class_name(AbstractDataTable $table): void
    {
        $column = $table->getColumnByName('actions');

        $this->assertInstanceOf(ActionColumn::class, $column);
        $this->assertSame('dt-center', $column->getClassName());
        $this->assertSame('dt-center not-exportable', $column->jsonSerialize()['className']);
    }

    /**
     * @return iterable<string, array{AbstractDataTable}>
     */
    public static function centeredActionsColumnProvider(): iterable
    {
        yield 'explicit column class name' => [
            self::tableWith(static fn (Actions $actions): Actions => $actions
                ->setColumnClassName('dt-center')
                ->add(Action::detail())),
        ];

        yield 'center alignment' => [self::beforeColumnsTable()];
    }

    #[Test]
    public function it_applies_the_actions_column_control_override(): void
    {
        $table = self::tableWith(static fn (Actions $actions): Actions => $actions
            ->setColumnControl(['colvisDropdown'])
            ->add(Action::detail()));

        $column = $table->getColumnByName('actions');

        $this->assertInstanceOf(ActionColumn::class, $column);
        $this->assertSame(['colvisDropdown'], $column->getColumnControl());
        $this->assertSame(['colvisDropdown'], $column->jsonSerialize()['columnControl']);
    }

    #[Test]
    public function it_leaves_the_actions_column_control_disabled_by_default(): void
    {
        $table = self::tableWith(static fn (Actions $actions): Actions => $actions->add(Action::detail()));

        $column = $table->getColumnByName('actions');

        $this->assertInstanceOf(ActionColumn::class, $column);
        $this->assertNull($column->getColumnControl());
        $this->assertSame([], $column->jsonSerialize()['columnControl']);
    }

    #[Test]
    public function it_keeps_explicit_action_entity_class(): void
    {
        $column = (new ExplicitActionEntityClassTestTable())->getColumnByName('actions');

        $this->assertInstanceOf(ActionColumn::class, $column);
        $this->assertSame('App\\Entity\\ExplicitBook', $column->jsonSerialize()['actions'][0]['entityClass']);
    }

    #[Test]
    public function it_splits_actions_into_two_columns_when_a_single_action_overrides_its_position(): void
    {
        $columns = $this->columnsOf(self::tableWith(static fn (Actions $actions): Actions => $actions
            ->add(Action::detail()->position(ActionsPosition::BeforeColumns))
            ->add(Action::delete())));

        $first = $columns[0];
        $last  = end($columns);

        $this->assertInstanceOf(ActionColumn::class, $first);
        $this->assertSame('actions_before', $first->getName());
        $this->assertCount(1, $first->getActions()->getActions());
        $this->assertSame('DETAIL', $first->jsonSerialize()['actions'][0]['type']);

        $this->assertInstanceOf(ActionColumn::class, $last);
        $this->assertSame('actions', $last->getName());
        $this->assertCount(1, $last->getActions()->getActions());
        $this->assertSame('DELETE', $last->jsonSerialize()['actions'][0]['type']);
    }

    #[Test]
    public function it_keeps_a_single_actions_column_when_all_actions_share_the_override(): void
    {
        $table = self::tableWith(static fn (Actions $actions): Actions => $actions->add(
            Action::detail()->position(ActionsPosition::BeforeColumns)
        ));

        $columns = $this->columnsOf($table);

        $this->assertInstanceOf(ActionColumn::class, $columns[0]);
        $this->assertSame('actions', $columns[0]->getName());
        $this->assertNull($table->getColumnByName('actions_before'));
    }

    /**
     * @param \Closure(Actions): Actions $configureActions
     */
    private static function tableWith(\Closure $configureActions): ConfigurableDataTable
    {
        return new ConfigurableDataTable([TextColumn::new('id')], $configureActions);
    }

    private static function beforeColumnsTable(): ConfigurableDataTable
    {
        return self::tableWith(static fn (Actions $actions): Actions => $actions
            ->position(ActionsPosition::BeforeColumns)
            ->alignment(ActionsAlignment::Center)
            ->add(Action::detail()));
    }

    /**
     * @return list<\Pentiminax\UX\DataTables\Contracts\ColumnInterface>
     */
    private function columnsOf(AbstractDataTable $table): array
    {
        return array_values($table->getConfiguredDataTable()->getColumns());
    }
}

#[AsDataTable(entityClass: 'App\\Entity\\AttributeBook')]
final class ExplicitActionEntityClassTestTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Action::detail()->setEntityClass('App\\Entity\\ExplicitBook')
        );
    }
}
