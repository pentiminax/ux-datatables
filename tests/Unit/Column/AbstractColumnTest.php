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
    public function permission_setter_is_chainable_and_stores_attribute(): void
    {
        $column = (new class extends AbstractColumn {})
            ->setType(ColumnType::STRING)
            ->setName('salary');

        $this->assertNull($column->getPermission());

        $this->assertSame($column, $column->permission('ROLE_HR'));
        $this->assertSame('ROLE_HR', $column->getPermission());
    }

    #[Test]
    public function permission_is_not_serialized_to_client(): void
    {
        $column = (new class extends AbstractColumn {})
            ->setType(ColumnType::STRING)
            ->setName('salary')
            ->permission('ROLE_HR');

        $this->assertArrayNotHasKey('permission', $column->jsonSerialize());
    }
}
