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
        // No setColumnAutoDetector called — should degrade gracefully
        $columns = $table->callAutoDetectColumns();

        $this->assertSame([], $columns);
    }

    #[Test]
    public function it_returns_empty_columns_with_null_detector(): void
    {
        $table = new AutoDetectTestDataTable();
        $table->setColumnAutoDetector(null);

        $columns = $table->callAutoDetectColumns();

        $this->assertSame([], $columns);
    }

    #[Test]
    public function it_returns_empty_columns_without_attribute(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->expects($this->never())->method('supports');
        $detector->expects($this->never())->method('detectColumns');

        $table = new AutoDetectNoAttributeDataTable();
        $table->setColumnAutoDetector($detector);

        $columns = $table->callAutoDetectColumns();

        $this->assertSame([], $columns);
    }

    #[Test]
    public function it_returns_empty_columns_when_not_supported(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(false);
        $detector->expects($this->never())->method('detectColumns');

        $table = new AutoDetectTestDataTable();
        $table->setColumnAutoDetector($detector);

        $columns = $table->callAutoDetectColumns();

        $this->assertSame([], $columns);
    }

    #[Test]
    public function it_returns_detected_columns(): void
    {
        $expected = [
            NumberColumn::new('id', 'ID'),
            TextColumn::new('name', 'Name'),
        ];

        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->with(\stdClass::class)->willReturn(true);
        $detector->method('detectColumns')->with(\stdClass::class, [])->willReturn($expected);

        $table = new AutoDetectTestDataTable();
        $table->setColumnAutoDetector($detector);

        $columns = $table->callAutoDetectColumns();

        $this->assertSame($expected, $columns);
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

        $table = new AutoDetectWithGroupsDataTable();
        $table->setColumnAutoDetector($detector);

        $table->callAutoDetectColumns();
    }

    #[Test]
    public function it_overrides_attribute_groups_with_explicit_groups(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(true);
        $detector
            ->expects($this->once())
            ->method('detectColumns')
            ->with(\stdClass::class, ['custom:group'])
            ->willReturn([]);

        $table = new AutoDetectWithGroupsDataTable();
        $table->setColumnAutoDetector($detector);

        $table->callAutoDetectColumns(['custom:group']);
    }
}
