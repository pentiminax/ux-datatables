<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ActionsTest extends TestCase
{
    public function test_new_actions_is_empty(): void
    {
        $actions = new Actions();

        $this->assertTrue($actions->isEmpty());
        $this->assertSame(0, $actions->count());
        $this->assertSame([], $actions->getActions());
    }

    public function test_add_action(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete());

        $this->assertFalse($actions->isEmpty());
        $this->assertSame(1, $actions->count());
    }

    public function test_remove_action(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete());
        $actions->remove(ActionType::Delete);

        $this->assertTrue($actions->isEmpty());
    }

    public function test_set_column_label(): void
    {
        $actions = new Actions();
        $actions->setColumnLabel('Operations');

        $this->assertSame('Operations', $actions->getColumnLabel());
    }

    public function test_default_column_label(): void
    {
        $actions = new Actions();

        $this->assertSame('Actions', $actions->getColumnLabel());
    }

    public function test_set_column_class_name(): void
    {
        $actions = new Actions();
        $actions->setColumnClassName('dt-center');

        $this->assertSame('dt-center', $actions->getColumnClassName());
    }

    public function test_json_serialize(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete()->setLabel('Remove'));

        $json = $actions->jsonSerialize();

        $this->assertCount(1, $json);
        $this->assertSame('DELETE', $json[0]['type']);
        $this->assertSame('Remove', $json[0]['label']);
    }

    public function test_fluent_api(): void
    {
        $actions = (new Actions())
            ->add(Action::delete())
            ->setColumnLabel('Ops');

        $this->assertSame(1, $actions->count());
        $this->assertSame('Ops', $actions->getColumnLabel());
    }

    public function test_add_replaces_action_of_same_type(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete()->setLabel('First'));
        $actions->add(Action::delete()->setLabel('Second'));

        $this->assertSame(1, $actions->count());
        $this->assertSame('Second', $actions->getActions()[0]->jsonSerialize()['label']);
    }
}
