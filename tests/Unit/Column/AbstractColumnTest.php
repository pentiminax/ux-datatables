<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractColumn::class)]
final class AbstractColumnTest extends TestCase
{
    #[Test]
    public function it_serializes_its_own_state(): void
    {
        $column = (new class extends AbstractColumn {})
            ->setType(ColumnType::STRING)
            ->setName('status')
            ->setTitle('Status')
            ->setExportable(false);

        $column->setCustomOption('format', 'badge');

        $this->assertSame([
            'className'     => 'not-exportable',
            'name'          => 'status',
            'orderable'     => true,
            'searchable'    => true,
            'title'         => 'Status',
            'type'          => ColumnType::STRING->value,
            'visible'       => true,
            'field'         => 'status',
            'customOptions' => ['format' => 'badge'],
        ], $column->jsonSerialize());
    }

    #[Test]
    public function it_exposes_all_interface_getters_with_defaults(): void
    {
        $column = (new class extends AbstractColumn {})
            ->setType(ColumnType::STRING)
            ->setName('foo');

        $this->assertSame(ColumnType::STRING, $column->getType());
        $this->assertTrue($column->isVisible());
        $this->assertTrue($column->isOrderable());
        $this->assertTrue($column->isExportable());
        $this->assertNull($column->getWidth());
        $this->assertNull($column->getClassName());
        $this->assertNull($column->getCellType());
        $this->assertNull($column->getDefaultContent());
        $this->assertSame([], $column->getCustomOptions());
        $this->assertNull($column->getCustomOption('unknown'));
        $this->assertArrayNotHasKey('columnControl', $column->jsonSerialize());
    }

    #[Test]
    public function it_can_disable_column_control_without_disabling_search(): void
    {
        $column = (new class extends AbstractColumn {})
            ->setType(ColumnType::STRING)
            ->setName('foo');

        $this->assertSame($column, $column->disableColumnControl());
        $this->assertTrue($column->isSearchable());
        $this->assertSame([], $column->jsonSerialize()['columnControl']);
    }

    #[Test]
    public function it_overrides_column_control_content_for_a_single_column(): void
    {
        $column = (new class extends AbstractColumn {})
            ->setType(ColumnType::STRING)
            ->setName('actions');

        $this->assertNull($column->getColumnControl());

        $this->assertSame($column, $column->setColumnControl(['colvisDropdown']));

        $this->assertSame(['colvisDropdown'], $column->getColumnControl());
        $this->assertSame(['colvisDropdown'], $column->jsonSerialize()['columnControl']);
    }

    #[Test]
    public function setting_column_control_content_wins_over_disabling_it_regardless_of_call_order(): void
    {
        $disableThenOverride = (new class extends AbstractColumn {})
            ->setType(ColumnType::STRING)
            ->setName('foo')
            ->disableColumnControl()
            ->setColumnControl(['order']);

        $overrideThenDisable = (new class extends AbstractColumn {})
            ->setType(ColumnType::STRING)
            ->setName('foo')
            ->setColumnControl(['order'])
            ->disableColumnControl();

        $this->assertSame(['order'], $disableThenOverride->jsonSerialize()['columnControl']);
        $this->assertSame(['order'], $overrideThenDisable->jsonSerialize()['columnControl']);
    }

    #[Test]
    public function it_exposes_mutated_getter_values(): void
    {
        $column = (new class extends AbstractColumn {})
            ->setType(ColumnType::DATE)
            ->setName('created_at')
            ->setVisible(false)
            ->setOrderable(false)
            ->setExportable(false)
            ->setWidth('120px')
            ->setClassName('text-center')
            ->setCellType('th')
            ->setDefaultContent('—');

        $column->setCustomOption('highlight', true);

        $this->assertSame(ColumnType::DATE, $column->getType());
        $this->assertFalse($column->isVisible());
        $this->assertFalse($column->isOrderable());
        $this->assertFalse($column->isExportable());
        $this->assertSame('120px', $column->getWidth());
        $this->assertSame('text-center', $column->getClassName());
        $this->assertSame('th', $column->getCellType());
        $this->assertSame('—', $column->getDefaultContent());
        $this->assertSame(['highlight' => true], $column->getCustomOptions());
        $this->assertTrue($column->getCustomOption('highlight'));
    }

    #[Test]
    public function permission_is_stored_server_side_and_never_serialized(): void
    {
        $column = (new class extends AbstractColumn {})
            ->setType(ColumnType::STRING)
            ->setName('salary');

        $this->assertNull($column->getPermission());

        $this->assertSame($column, $column->permission('ROLE_HR'));
        $this->assertSame('ROLE_HR', $column->getPermission());
        $this->assertArrayNotHasKey('permission', $column->jsonSerialize());
    }

    /**
     * setField(), setColumnControl(), setClassName(), setCustomOption(), setVisible(), and
     * disableGlobalSearch() are documented public API (see columns/overview.mdx) inherited
     * unchanged by every concrete column type. A class-level @internal here previously made
     * static analysis tools such as Psalm flag every one of those calls from application code
     * as touching an internal class, even though only createWithType() is actually meant to
     * stay restricted to the bundle's own column types.
     */
    #[Test]
    public function the_class_itself_is_not_marked_internal_but_create_with_type_still_is(): void
    {
        $class = new \ReflectionClass(AbstractColumn::class);

        $this->assertStringNotContainsString('@internal', (string) $class->getDocComment());

        $factory = $class->getMethod('createWithType');
        $this->assertStringContainsString('@internal', (string) $factory->getDocComment());
    }
}
