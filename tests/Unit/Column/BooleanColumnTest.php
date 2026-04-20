<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BooleanColumn::class)]
final class BooleanColumnTest extends TestCase
{
    #[Test]
    public function it_has_neutral_default_serialization(): void
    {
        $data = BooleanColumn::new('active', 'Active')->jsonSerialize();

        $this->assertSame('num', $data['type']);
        $this->assertArrayNotHasKey('customOptions', $data);
    }

    #[Test]
    public function it_can_configure_switch_state_and_ajax(): void
    {
        $data = BooleanColumn::new('active')
            ->renderAsSwitch(true)
            ->setToggleAjax('uuid', 'post')
            ->jsonSerialize();

        $this->assertTrue($data['customOptions']['renderAsSwitch']);
        $this->assertTrue($data['customOptions']['defaultState']);
        $this->assertSame('uuid', $data['customOptions']['toggleIdField']);
        $this->assertSame('POST', $data['customOptions']['toggleMethod']);
    }

    #[Test]
    public function it_is_not_rendered_as_switch_by_default(): void
    {
        $column = BooleanColumn::new('active');

        $this->assertFalse($column->isRenderedAsSwitch());
        $this->assertFalse($column->getDefaultState());
    }

    #[Test]
    public function it_can_configure_entity_class(): void
    {
        $data = BooleanColumn::new('active')
            ->setEntityClass('App\\Entity\\User')
            ->jsonSerialize();

        $this->assertSame('App\\Entity\\User', $data['customOptions']['entityClass']);
    }

    #[Test]
    public function it_serializes_toggle_field_in_custom_options(): void
    {
        $column = BooleanColumn::new('active');
        $column->setCustomOption(BooleanColumn::OPTION_TOGGLE_FIELD, 'isActive');

        $data = $column->jsonSerialize();

        $this->assertSame('isActive', $data['customOptions']['toggleField']);
    }

    #[Test]
    public function it_returns_toggle_field_via_getter(): void
    {
        $column = BooleanColumn::new('active');
        $this->assertNull($column->getToggleField());

        $column->setCustomOption(BooleanColumn::OPTION_TOGGLE_FIELD, 'isActive');
        $this->assertSame('isActive', $column->getToggleField());
    }
}
