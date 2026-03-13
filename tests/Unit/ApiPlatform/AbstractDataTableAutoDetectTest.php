<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\AutoDetectNoAttributeDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\AutoDetectTestDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\AutoDetectWithGroupsDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableAutoDetectTest extends TestCase
{
    #[Test]
    public function it_returns_empty_columns_without_detector(): void
    {
        $table = new AutoDetectTestDataTable();

        $this->assertSame([], $table->getDataTable()->getColumns());
    }

    #[Test]
    public function it_returns_empty_columns_without_attribute(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->expects($this->never())->method('supports');
        $detector->expects($this->never())->method('detectColumns');

        $table = new AutoDetectNoAttributeDataTable(columnAutoDetector: $detector);

        $this->assertSame([], $table->getDataTable()->getColumns());
    }

    #[Test]
    public function it_returns_empty_columns_when_not_supported(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(false);
        $detector->expects($this->never())->method('detectColumns');

        $table = new AutoDetectTestDataTable(columnAutoDetector: $detector);

        $this->assertSame([], $table->getDataTable()->getColumns());
    }

    #[Test]
    public function it_returns_detected_columns(): void
    {
        $detected = [
            NumberColumn::new('id', 'ID'),
            TextColumn::new('name', 'Name'),
        ];

        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->with(\stdClass::class)->willReturn(true);
        $detector->method('detectColumns')->with(\stdClass::class, [])->willReturn($detected);

        $table = new AutoDetectTestDataTable(columnAutoDetector: $detector);
        $columns = $table->getDataTable()->getColumns();

        $this->assertCount(2, $columns);
        $this->assertSame('id', $columns[0]['name']);
        $this->assertSame('name', $columns[1]['name']);
    }

    #[Test]
    public function it_uses_attribute_serialization_groups(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(true);
        $detector
            ->expects($this->once())
            ->method('detectColumns')
            ->with(\stdClass::class, ['product:list'])
            ->willReturn([]);

        new AutoDetectWithGroupsDataTable(columnAutoDetector: $detector);
    }
}
