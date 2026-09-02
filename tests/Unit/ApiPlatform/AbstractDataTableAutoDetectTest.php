<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use Pentiminax\UX\DataTables\ApiPlatform\ColumnAutoDetector;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\AutoDetectNoAttributeDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\AutoDetectTestDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\AutoDetectWithGroupsDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\AutoDetectWithoutApiPlatformOptInDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableAutoDetectTest extends TestCase
{
    /**
     * @param class-string<AbstractDataTable> $tableClass
     * @param bool|null                       $supports   null when no detector is registered at all
     */
    #[Test]
    #[DataProvider('provideTablesWithoutDetectedColumns')]
    public function it_returns_empty_columns(string $tableClass, ?bool $supports): void
    {
        $table = new $tableClass(columnAutoDetector: null === $supports ? null : $this->detector($supports));

        $this->assertSame([], $table->getConfiguredDataTable()->getColumns());
    }

    /**
     * @return iterable<string, array{class-string<AbstractDataTable>, bool|null}>
     */
    public static function provideTablesWithoutDetectedColumns(): iterable
    {
        yield 'without detector' => [AutoDetectTestDataTable::class, null];
        yield 'without attribute' => [AutoDetectNoAttributeDataTable::class, true];
        yield 'without API Platform opt-in' => [AutoDetectWithoutApiPlatformOptInDataTable::class, true];
        yield 'unsupported entity' => [AutoDetectTestDataTable::class, false];
    }

    #[Test]
    public function it_returns_detected_columns(): void
    {
        $detector = $this->createStub(ColumnAutoDetector::class);
        $detector->method('supports')->willReturn(true);
        $detector->method('detectColumns')->willReturn([
            NumberColumn::new('id', 'ID'),
            TextColumn::new('name', 'Name'),
        ]);

        $dataTable = (new AutoDetectTestDataTable(columnAutoDetector: $detector))->getConfiguredDataTable();

        $this->assertSame(['id', 'name'], array_keys($dataTable->getColumns()));
        $this->assertSame(['id', 'name'], array_map(
            static fn (object $column): string => $column->getName(),
            array_values($dataTable->getColumns())
        ));
        $this->assertSame(['id', 'name'], array_column($dataTable->getColumnDefinitions(), 'name'));
    }

    #[Test]
    public function it_uses_attribute_serialization_groups(): void
    {
        $detector = $this->createMock(ColumnAutoDetector::class);
        $detector->method('supports')->willReturn(true);
        $detector
            ->expects($this->once())
            ->method('detectColumns')
            ->with(\stdClass::class, ['product:list'])
            ->willReturn([]);

        (new AutoDetectWithGroupsDataTable(columnAutoDetector: $detector))->getConfiguredDataTable();
    }

    /**
     * Returns columns whenever it is consulted, so an empty column list proves it was not.
     */
    private function detector(bool $supports): ColumnAutoDetector
    {
        $detector = $this->createStub(ColumnAutoDetector::class);
        $detector->method('supports')->willReturn($supports);
        $detector->method('detectColumns')->willReturn([TextColumn::new('detected')]);

        return $detector;
    }
}
