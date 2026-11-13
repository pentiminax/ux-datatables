<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\FeatureAwareDataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableResponder;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DataTableResponder::class)]
final class DataTableResponderTest extends TestCase
{
    #[Test]
    public function it_returns_the_response_of_a_datatables_request(): void
    {
        $response = (new DataTableResponder())->respond($this->table(), new Request(query: ['draw' => 3]));

        $this->assertNotNull($response);
        $this->assertSame(
            ['draw' => 3, 'recordsTotal' => 10, 'recordsFiltered' => 4, 'data' => [['id' => 1]]],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function it_returns_null_when_the_request_carries_no_datatables_payload(): void
    {
        $this->assertNull((new DataTableResponder())->respond($this->table(), new Request()));
    }

    #[Test]
    public function it_records_the_ajax_query_with_the_profiler(): void
    {
        $profiler = new DataTableProfiler();
        $table    = $this->table();
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(profiler: $profiler));

        (new DataTableResponder())->respond($table, new Request(query: ['draw' => 3]));

        $queries = $profiler->getAjaxQueries();

        $this->assertCount(1, $queries);
        $this->assertSame(10, $queries[0]['recordsTotal']);
        $this->assertSame(4, $queries[0]['recordsFiltered']);
        $this->assertSame(1, $queries[0]['rowCount']);
    }

    #[Test]
    public function it_raises_no_deprecation(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            (new DataTableResponder())->respond($this->table(), new Request(query: ['draw' => 3]));
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], array_values(array_filter($deprecations, static fn (string $message): bool => !str_contains($message, 'without an authorization checker'))));
    }

    private function table(): ConfigurableDataTable
    {
        return new ConfigurableDataTable([TextColumn::new('id')], dataProvider: new class implements DataProviderInterface, FeatureAwareDataProviderInterface {
            public function supports(DataSourceFeature $feature): bool
            {
                return true;
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                return new DataTableResult(recordsTotal: 10, recordsFiltered: 4, data: [['id' => 1]]);
            }
        });
    }
}
