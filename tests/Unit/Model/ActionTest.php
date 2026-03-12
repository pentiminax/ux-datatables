<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Model\Action;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ActionTest extends TestCase
{
    public function test_delete_factory_creates_action_with_default_values(): void
    {
        $action = Action::delete();

        $this->assertSame(ActionType::Delete, $action->getType());

        $json = $action->jsonSerialize();

        $this->assertSame('DELETE', $json['type']);
        $this->assertSame('Delete', $json['label']);
        $this->assertSame('btn btn-danger', $json['cssClass']);
        $this->assertSame('id', $json['idField']);
        $this->assertArrayNotHasKey('icon', $json);
        $this->assertArrayNotHasKey('confirm', $json);
        $this->assertArrayNotHasKey('displayCondition', $json);
        $this->assertArrayNotHasKey('entityClass', $json);
    }

    public function test_fluent_setters(): void
    {
        $action = Action::delete()
            ->setLabel('Supprimer')
            ->setCssClass('btn btn-sm btn-danger')
            ->setIcon('bi bi-trash')
            ->askConfirmation('Are you sure?')
            ->setIdField('uuid');

        $json = $action->jsonSerialize();

        $this->assertSame('Supprimer', $json['label']);
        $this->assertSame('btn btn-sm btn-danger', $json['cssClass']);
        $this->assertSame('bi bi-trash', $json['icon']);
        $this->assertSame('Are you sure?', $json['confirm']);
        $this->assertSame('uuid', $json['idField']);
    }

    public function test_display_if_sets_condition(): void
    {
        $action = Action::delete()
            ->displayIf('isDeletable', true);

        $json = $action->jsonSerialize();

        $this->assertSame(['field' => 'isDeletable', 'value' => true], $json['displayCondition']);
    }

    public function test_set_entity_class_strips_leading_backslash(): void
    {
        $action = Action::delete()
            ->setEntityClass('\\App\\Entity\\User');

        $json = $action->jsonSerialize();

        $this->assertSame('App\\Entity\\User', $json['entityClass']);
    }

    public function test_set_entity_class_without_leading_backslash(): void
    {
        $action = Action::delete()
            ->setEntityClass('App\\Entity\\User');

        $json = $action->jsonSerialize();

        $this->assertSame('App\\Entity\\User', $json['entityClass']);
    }

    public function test_json_serialize_omits_null_optional_fields(): void
    {
        $json = Action::delete()->jsonSerialize();

        $this->assertArrayHasKey('type', $json);
        $this->assertArrayHasKey('label', $json);
        $this->assertArrayHasKey('cssClass', $json);
        $this->assertArrayHasKey('idField', $json);
        $this->assertArrayNotHasKey('icon', $json);
        $this->assertArrayNotHasKey('confirm', $json);
        $this->assertArrayNotHasKey('displayCondition', $json);
        $this->assertArrayNotHasKey('entityClass', $json);
    }
}
