<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(BooleanColumn::class)]
final class BooleanColumnTest extends DataTableTestCase
{
    #[Test]
    public function it_has_neutral_default_serialization(): void
    {
        $column = BooleanColumn::new('active', 'Active');

        $this->assertColumnHeader($column, 'num', 'active', 'Active');
        $this->assertCustomOptions([], $column);
    }

    #[Test]
    public function it_can_configure_switch_state_and_ajax(): void
    {
        $column = BooleanColumn::new('active')
            ->renderAsSwitch(true)
            ->setToggleAjax('uuid', 'post');

        $this->assertCustomOption(true, 'renderAsSwitch', $column);
        $this->assertCustomOption(true, 'defaultState', $column);
        $this->assertCustomOption('uuid', 'toggleIdField', $column);
        $this->assertCustomOption('POST', 'toggleMethod', $column);
    }

    #[Test]
    public function it_is_not_rendered_as_switch_by_default(): void
    {
        $column = BooleanColumn::new('active');

        $this->assertFalse($column->isRenderedAsSwitch());
        $this->assertFalse($column->getDefaultState());
    }

    #[Test]
    public function it_keeps_entity_class_server_side_without_serializing_it(): void
    {
        $column = BooleanColumn::new('active')
            ->setEntityClass('App\\Entity\\User');

        $this->assertSame('App\\Entity\\User', $column->getEntityClass());
        $this->assertNoCustomOption('entityClass', $column);
    }

    #[Test]
    public function it_exposes_the_toggle_field_through_the_getter_and_the_payload(): void
    {
        $column = BooleanColumn::new('active');
        $this->assertNull($column->getToggleField());

        $column->setCustomOption(BooleanColumn::OPTION_TOGGLE_FIELD, 'isActive');

        $this->assertSame('isActive', $column->getToggleField());
        $this->assertCustomOption('isActive', 'toggleField', $column);
    }
}
