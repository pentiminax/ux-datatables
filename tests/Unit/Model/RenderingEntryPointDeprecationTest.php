<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
#[CoversClass(DataTable::class)]
final class RenderingEntryPointDeprecationTest extends TestCase
{
    use CollectsDeprecations;

    #[Test]
    public function it_deprecates_get_data_table(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);

        $deprecations = $this->withoutAuthorizationFallback($this->deprecationsOf(static fn () => $table->getDataTable()));

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('getConfiguredDataTable()', $deprecations[0]);
        $this->assertStringContainsString('render_datatable()', $deprecations[0]);
    }

    #[Test]
    public function it_deprecates_prepare_for_rendering(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);

        $deprecations = $this->withoutAuthorizationFallback($this->deprecationsOf(static fn () => $table->prepareForRendering()));

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('getConfiguredDataTable()', $deprecations[0]);
    }

    #[Test]
    public function it_deprecates_the_api_platform_server_side_flag(): void
    {
        $table = new DataTable('users');

        $deprecations = $this->deprecationsOf(static fn () => $table->apiPlatformServerSide());

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('the bundle sets this flag itself', $deprecations[0]);
        $this->assertTrue($table->getOption('apiPlatformServerSide'));
    }

    #[Test]
    public function it_raises_no_such_deprecation_on_the_bundle_render_path(): void
    {
        $table = new ConfigurableDataTable([TextColumn::new('id')]);

        $deprecations = $this->deprecationsOf(static function () use ($table): void {
            $table->prepareDataTableForRendering();
            $table->getConfiguredDataTable();
            (new DataTable('users'))->markApiPlatformServerSide();
        });

        $this->assertSame([], $this->withoutAuthorizationFallback($deprecations));
    }
}
