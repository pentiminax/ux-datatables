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
}
