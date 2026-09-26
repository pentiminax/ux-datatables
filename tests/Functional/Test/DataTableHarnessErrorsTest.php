<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Functional\Test;

use Pentiminax\UX\DataTables\Test\DataTableTestCase;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessUnregisteredDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class DataTableHarnessErrorsTest extends DataTableTestCase
{
    public function test_an_unregistered_table_names_the_attribute(): void
    {
        static::createClient();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/HarnessUnregisteredDataTable.+AsDataTable/s');

        $this->dataTable(HarnessUnregisteredDataTable::class)->fetch();
    }

    public function test_an_unknown_order_column_lists_the_valid_names(): void
    {
        static::createClient();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"author".+id, title/s');

        $this->dataTable(HarnessServerSideDataTable::class)->orderBy('author')->fetch();
    }

    public function test_a_page_number_below_one_is_rejected(): void
    {
        static::createClient();

        $this->expectException(\LogicException::class);

        $this->dataTable(HarnessServerSideDataTable::class)->page(0);
    }

    public function test_a_failed_request_reports_the_status_and_the_body(): void
    {
        static::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/returned HTTP 404/');

        $this->dataTable(HarnessServerSideDataTable::class)
            ->query(['table' => 'not-a-token'])
            ->fetch();
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
