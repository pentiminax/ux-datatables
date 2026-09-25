<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableTestCase;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class DataTableResponseAssertionsTest extends DataTableTestCase
{
    public function test_the_assertions_chain(): void
    {
        static::createClient();

        $this->dataTable(HarnessServerSideDataTable::class)
            ->orderBy('id')
            ->fetch()
            ->assertRowCount(3)
            ->assertRecordsTotal(3)
            ->assertRecordsFiltered(3)
            ->assertRowsContain(['title' => 'Symfony 7'])
            ->assertColumnValues('title', ['Symfony 7', 'UX in Action', 'Doctrine Deep Dive']);
    }

    public function test_an_absent_column_fails_with_the_available_names(): void
    {
        static::createClient();

        $response = $this->dataTable(HarnessServerSideDataTable::class)->fetch();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/"author".+id, title/s');

        $response->assertColumnValues('author', []);
    }

    public function test_an_unmatched_row_subset_reports_what_was_expected(): void
    {
        static::createClient();

        $response = $this->dataTable(HarnessServerSideDataTable::class)->fetch();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/No row matches/');

        $response->assertRowsContain(['title' => 'Nothing like this']);
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
