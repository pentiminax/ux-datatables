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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
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
    #[TestWith([AfterColumnsActionsTestTable::class, 1])]
    #[TestWith([BeforeColumnsActionsTestTable::class, 0])]
    public function it_places_the_actions_column_at_the_configured_position(string $tableClass, int $index): void
    {
        $columns = $this->columnsOf(new $tableClass());

        $this->assertInstanceOf(ActionColumn::class, $columns[$index]);
    }

    #[Test]
    #[TestWith([ActionsColumnClassNameTestTable::class])]
    #[TestWith([BeforeColumnsActionsTestTable::class])]
    public function it_applies_the_actions_column_class_name(string $tableClass): void
    {
        $column = (new $tableClass())->getColumnByName('actions');

        $this->assertInstanceOf(ActionColumn::class, $column);
        $this->assertSame('dt-center', $column->getClassName());
        $this->assertSame('dt-center not-exportable', $column->jsonSerialize()['className']);
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
        $columns = $this->columnsOf(new PerActionPositionTestTable());

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
        $table = new SingleOverrideActionPositionTestTable();

        $columns = $this->columnsOf($table);

        $this->assertInstanceOf(ActionColumn::class, $columns[0]);
        $this->assertSame('actions', $columns[0]->getName());
        $this->assertNull($table->getColumnByName('actions_before'));
    }

    /**
     * @return list<\Pentiminax\UX\DataTables\Contracts\ColumnInterface>
     */
    private function columnsOf(AbstractDataTable $table): array
    {
        return array_values($table->getConfiguredDataTable()->getColumns());
    }
}

final class AfterColumnsActionsTestTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::detail());
    }
}

final class BeforeColumnsActionsTestTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->position(ActionsPosition::BeforeColumns)
            ->alignment(ActionsAlignment::Center)
            ->add(Action::detail());
    }
}

final class ActionsColumnClassNameTestTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setColumnClassName('dt-center')
            ->add(Action::detail());
    }
}

final class PerActionPositionTestTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Action::detail()->position(ActionsPosition::BeforeColumns))
            ->add(Action::delete());
    }
}

final class SingleOverrideActionPositionTestTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::detail()->position(ActionsPosition::BeforeColumns));
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
