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
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableActionsTest extends TestCase
{
    #[Test]
    public function it_applies_the_configured_actions_column_class_name(): void
    {
        $table = new ActionsColumnClassNameTestTable();

        $column = $table->getColumnByName('actions');

        $this->assertInstanceOf(ActionColumn::class, $column);
        $this->assertSame('dt-center', $column->getClassName());

        $serialized = $column->jsonSerialize();

        $this->assertSame('dt-center not-exportable', $serialized['className']);
    }

    #[Test]
    public function it_keeps_explicit_action_entity_class(): void
    {
        $table = new ExplicitActionEntityClassTestTable();

        $column = $table->getColumnByName('actions');

        $this->assertInstanceOf(ActionColumn::class, $column);
        $this->assertSame('App\\Entity\\ExplicitBook', $column->jsonSerialize()['actions'][0]['entityClass']);
    }

    #[Test]
    public function it_appends_the_actions_column_by_default(): void
    {
        $columns = array_values((new AfterColumnsActionsTestTable())->getConfiguredDataTable()->getColumns());

        $this->assertInstanceOf(ActionColumn::class, end($columns));
    }

    #[Test]
    public function it_prepends_the_actions_column_when_positioned_before_columns(): void
    {
        $columns = array_values((new BeforeColumnsActionsTestTable())->getConfiguredDataTable()->getColumns());

        $this->assertInstanceOf(ActionColumn::class, $columns[0]);
    }

    #[Test]
    public function it_appends_the_alignment_class_to_the_actions_column(): void
    {
        $column = (new BeforeColumnsActionsTestTable())->getColumnByName('actions');

        $this->assertInstanceOf(ActionColumn::class, $column);
        $this->assertSame('dt-center', $column->getClassName());
    }

    #[Test]
    public function it_splits_actions_into_two_columns_when_a_single_action_overrides_its_position(): void
    {
        $columns = array_values((new PerActionPositionTestTable())->getConfiguredDataTable()->getColumns());

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

        $columns = array_values($table->getConfiguredDataTable()->getColumns());

        $this->assertInstanceOf(ActionColumn::class, $columns[0]);
        $this->assertSame('actions', $columns[0]->getName());
        $this->assertNull($table->getColumnByName('actions_before'));
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
