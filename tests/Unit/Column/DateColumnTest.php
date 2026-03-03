<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\DateColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DateColumn::class)]
final class DateColumnTest extends TestCase
{
    #[Test]
    public function it_returns_default_format_when_no_format_provided(): void
    {
        $column = DateColumn::new('createdAt');

        $this->assertSame(DateColumn::DEFAULT_DATE_FORMAT, $column->getFormat());
    }

    #[Test]
    public function it_exposes_date_format_in_serialization(): void
    {
        $column = DateColumn::new('createdAt')->setFormat('d/m/Y');

        $this->assertSame('d/m/Y', $column->jsonSerialize()[DateColumn::OPTION_DATE_FORMAT]);
    }
}
