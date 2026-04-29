<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
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
