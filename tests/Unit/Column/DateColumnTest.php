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

    #[Test]
    public function it_is_not_relative_by_default(): void
    {
        $column = DateColumn::new('lastLoginAt');

        $this->assertFalse($column->isRelative());
        $this->assertCustomOptions([], $column);
    }

    #[Test]
    public function it_serializes_iso_dates_when_rendering_is_relative(): void
    {
        $column = DateColumn::new('lastLoginAt')->relative();

        $this->assertTrue($column->isRelative());
        $this->assertSame(\DateTimeInterface::ATOM, $column->getFormat());
        $this->assertCustomOption(true, 'relative', $column);
    }

    #[Test]
    public function it_ignores_a_custom_format_while_rendering_is_relative(): void
    {
        $column = DateColumn::new('lastLoginAt')->setFormat('d/m/Y')->relative();

        $this->assertSame(\DateTimeInterface::ATOM, $column->getFormat());
    }

    #[Test]
    public function it_restores_the_configured_format_when_relative_rendering_is_disabled(): void
    {
        $column = DateColumn::new('lastLoginAt')->setFormat('d/m/Y')->relative()->relative(false);

        $this->assertFalse($column->isRelative());
        $this->assertSame('d/m/Y', $column->getFormat());
        $this->assertCustomOptions(['dateFormat' => 'd/m/Y'], $column);
    }
}
