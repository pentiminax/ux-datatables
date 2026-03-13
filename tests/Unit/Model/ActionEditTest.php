<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Model\Action;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ActionEditTest extends TestCase
{
    public function test_edit_factory_creates_action_with_default_values(): void
    {
        $action = Action::edit();

        $this->assertSame(ActionType::Edit, $action->getType());

        $json = $action->jsonSerialize();

        $this->assertSame('EDIT', $json['type']);
        $this->assertSame('Edit', $json['label']);
        $this->assertSame('btn btn-warning', $json['className']);
        $this->assertSame('id', $json['idField']);
        $this->assertSame([], $json['htmlAttributes']);
    }

    public function test_edit_factory_with_custom_values(): void
    {
        $action = Action::edit('Modifier', 'btn btn-sm btn-warning');

        $json = $action->jsonSerialize();

        $this->assertSame('EDIT', $json['type']);
        $this->assertSame('Modifier', $json['label']);
        $this->assertSame('btn btn-sm btn-warning', $json['className']);
    }

    public function test_edit_action_supports_all_fluent_setters(): void
    {
        $action = Action::edit()
            ->setLabel('Modifier')
            ->setIcon('bi bi-pencil')
            ->setEntityClass('App\\Entity\\Product')
            ->setIdField('uuid');

        $json = $action->jsonSerialize();

        $this->assertSame('Modifier', $json['label']);
        $this->assertSame('bi bi-pencil', $json['icon']);
        $this->assertSame('App\\Entity\\Product', $json['entityClass']);
        $this->assertSame('uuid', $json['idField']);
    }
}
