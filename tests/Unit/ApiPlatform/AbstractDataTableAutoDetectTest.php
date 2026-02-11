<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use PHPUnit\Framework\TestCase;

class AbstractDataTableAutoDetectTest extends TestCase
{
    public function testAutoDetectColumnsReturnsEmptyWithoutDetector(): void
    {
        $table = new AutoDetectTestDataTable();
        // No setColumnAutoDetector called — should degrade gracefully
        $columns = $table->callAutoDetectColumns();

        $this->assertSame([], $columns);
    }

    public function testAutoDetectColumnsReturnsEmptyWithNullDetector(): void
    {
        $table = new AutoDetectTestDataTable();
        $table->setColumnAutoDetector(null);

        $columns = $table->callAutoDetectColumns();

        $this->assertSame([], $columns);
    }

    public function testAutoDetectColumnsReturnsEmptyWithoutAttribute(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->expects($this->never())->method('supports');
        $detector->expects($this->never())->method('detectColumns');

        $table = new AutoDetectNoAttributeDataTable();
        $table->setColumnAutoDetector($detector);

        $columns = $table->callAutoDetectColumns();

        $this->assertSame([], $columns);
    }

    public function testAutoDetectColumnsReturnsEmptyWhenNotSupported(): void
    {
        $detector = $this->createMock(ColumnAutoDetectorInterface::class);
        $detector->method('supports')->willReturn(false);
        $detector->expects($this->never())->method('detectColumns');

        $table = new AutoDetectTestDataTable();
        $table->setColumnAutoDetector($detector);

        $columns = $table->callAutoDetectColumns();

        $this->assertSame([], $columns);
    }

    public function testAutoDetectColumnsReturnsDetectedColumns(): void
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

    public function testAutoDetectColumnsUsesAttributeSerializationGroups(): void
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

    public function testAutoDetectColumnsExplicitGroupsOverrideAttribute(): void
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

#[AsDataTable(entityClass: \stdClass::class)]
class AutoDetectTestDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return $this->autoDetectColumns();
    }

    /**
     * @param string[] $groups
     *
     * @return AbstractColumn[]
     */
    public function callAutoDetectColumns(array $groups = []): array
    {
        return $this->autoDetectColumns($groups);
    }
}

class AutoDetectNoAttributeDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    /**
     * @return AbstractColumn[]
     */
    public function callAutoDetectColumns(): array
    {
        return $this->autoDetectColumns();
    }
}

#[AsDataTable(entityClass: \stdClass::class, serializationGroups: ['product:list'])]
class AutoDetectWithGroupsDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return $this->autoDetectColumns();
    }

    /**
     * @param string[] $groups
     *
     * @return AbstractColumn[]
     */
    public function callAutoDetectColumns(array $groups = []): array
    {
        return $this->autoDetectColumns($groups);
    }
}
