<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataSourceMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataSourceMode::class)]
final class DataSourceModeTest extends TestCase
{
    private const string NOT_INSTALLED = 'not installed';

    /**
     * @param \Closure(DataTable): DataTable $configure
     * @param string|null                    $collectionUrl the URL API Platform resolves, or NOT_INSTALLED
     */
    #[Test]
    #[DataProvider('provideTables')]
    public function it_resolves_where_the_rows_come_from(
        \Closure $configure,
        ?AsDataTable $asDataTable,
        ?string $collectionUrl,
        DataSourceMode $expected,
    ): void {
        $urlResolver = null;
        if (self::NOT_INSTALLED !== $collectionUrl) {
            $urlResolver = $this->createStub(ApiResourceCollectionUrlResolver::class);
            $urlResolver->method('resolveCollectionUrl')->willReturn($collectionUrl);
        }

        $table = $configure(new DataTable('books'));

        $this->assertSame($expected, DataSourceMode::resolve($table, $asDataTable, $urlResolver));
    }

    public static function provideTables(): iterable
    {
        $plain       = static fn (DataTable $table): DataTable => $table;
        $serverSide  = static fn (DataTable $table): DataTable => $table->serverSide();
        $fluentOptIn = static fn (DataTable $table): DataTable => $table->apiPlatform();
        $attribute   = new AsDataTable(entityClass: \stdClass::class);
        $optedIn     = new AsDataTable(entityClass: \stdClass::class, apiPlatform: true);

        yield 'nothing declared' => [$plain, null, self::NOT_INSTALLED, DataSourceMode::ClientHydrated];

        yield 'entity without opt-in' => [$plain, $attribute, '/api/books', DataSourceMode::ClientHydrated];

        yield 'server side' => [$serverSide, $attribute, '/api/books', DataSourceMode::ServerSide];

        yield 'ajax' => [
            static fn (DataTable $table): DataTable => $table->ajax('/books.json'),
            $optedIn,
            '/api/books',
            DataSourceMode::Ajax,
        ];

        yield 'ajax over inline data' => [
            static fn (DataTable $table): DataTable => $table->data([['id' => 1]])->ajax('/books.json'),
            null,
            self::NOT_INSTALLED,
            DataSourceMode::Ajax,
        ];

        yield 'inline data' => [
            static fn (DataTable $table): DataTable => $table->data([['id' => 1]])->serverSide(),
            $optedIn,
            '/api/books',
            DataSourceMode::Inline,
        ];

        yield 'attribute opt-in' => [$plain, $optedIn, '/api/books', DataSourceMode::ApiPlatform];

        yield 'attribute opt-in on a server-side table' => [$serverSide, $optedIn, '/api/books', DataSourceMode::ApiPlatform];

        yield 'fluent opt-in' => [$fluentOptIn, $attribute, '/api/books', DataSourceMode::ApiPlatform];

        yield 'fluent opt-in without an entity' => [$fluentOptIn, null, '/api/books', DataSourceMode::ApiPlatformUnavailable];

        yield 'opt-in, no collection operation' => [$plain, $optedIn, null, DataSourceMode::ApiPlatformUnavailable];

        yield 'opt-in, no collection operation, server side' => [$serverSide, $optedIn, null, DataSourceMode::ApiPlatformUnavailable];

        yield 'opt-in, API Platform not installed' => [$fluentOptIn, $optedIn, self::NOT_INSTALLED, DataSourceMode::ApiPlatformUnavailable];
    }

    #[Test]
    public function it_looks_up_no_collection_for_a_table_that_did_not_opt_in(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolver::class);
        $urlResolver->expects($this->never())->method('resolveCollectionUrl');

        $mode = DataSourceMode::resolve(new DataTable('books'), new AsDataTable(entityClass: \stdClass::class), $urlResolver);

        $this->assertSame(DataSourceMode::ClientHydrated, $mode);
    }
}
