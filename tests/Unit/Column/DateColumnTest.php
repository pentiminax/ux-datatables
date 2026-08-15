<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(DateColumn::class)]
final class DateColumnTest extends DataTableTestCase
{
    #[Test]
    public function it_keeps_the_default_format_out_of_custom_options(): void
    {
        $column = DateColumn::new('createdAt');

        $this->assertSame(DateColumn::DEFAULT_DATE_FORMAT, $column->getFormat());
        $this->assertCustomOptions([], $column);
    }

    #[Test]
    public function it_exposes_a_custom_date_format_in_serialization(): void
    {
        $column = DateColumn::new('createdAt')->setFormat('d/m/Y');

        $this->assertCustomOption('d/m/Y', 'dateFormat', $column);
    }
}
