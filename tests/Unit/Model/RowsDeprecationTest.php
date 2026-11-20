<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class RowsDeprecationTest extends TestCase
{
    use CollectsDeprecations;

    #[Test]
    public function it_deprecates_rows_and_still_embeds_the_rows(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);

        $deprecations = $this->withoutAuthorizationFallback($this->deprecationsOf(static fn () => $table->rows([['id' => 1]])));

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('"rows" argument of "render_datatable()"', $deprecations[0]);
        $this->assertStringContainsString('Removed in 2.0.', $deprecations[0]);
        $this->assertSame([['id' => 1]], $table->getConfiguredDataTable()->getOption('data'));
    }

    #[Test]
    public function it_deprecates_set_data_toward_the_render_argument_and_still_embeds_the_rows(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);

        $deprecations = $this->withoutAuthorizationFallback($this->deprecationsOf(static fn () => $table->setData([['id' => 1]])));

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('"rows" argument of "render_datatable()"', $deprecations[0]);
        $this->assertSame([['id' => 1]], $table->getConfiguredDataTable()->getOption('data'));
    }
}
