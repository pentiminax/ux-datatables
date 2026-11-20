<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableApiPlatformUnavailableTest extends TestCase
{
    use BuildsRuntime;

    #[Test]
    #[DataProvider('provideUnresolvableCollections')]
    public function it_refuses_to_render_an_api_platform_table_without_a_collection(
        string $tableClass,
        ?ApiResourceCollectionUrlResolver $urlResolver,
    ): void {
        $runtime = self::buildRuntime(collectionUrlResolver: $urlResolver);
        $table   = new $tableClass();

        try {
            $runtime->render($table);
            $this->fail('Rendering an API Platform table without a collection must throw.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('exposes no collection operation', $exception->getMessage());
        }

        $this->assertNull($runtime->definition($table)->table->getOption('data'));
    }

    #[Test]
    #[DataProvider('provideUnresolvableCollections')]
    public function it_answers_not_found_to_an_ajax_request_after_a_refused_render(
        string $tableClass,
        ?ApiResourceCollectionUrlResolver $urlResolver,
    ): void {
        $runtime = self::buildRuntime(collectionUrlResolver: $urlResolver);
        $table   = new $tableClass();

        try {
            $runtime->render($table);
        } catch (\LogicException) {
        }

        $this->expectException(NotFoundHttpException::class);

        $runtime->respond($table, $this->dataTablesRequest());
    }

    #[Test]
    #[DataProvider('provideUnresolvableCollections')]
    public function it_answers_not_found_to_an_ajax_data_request(
        string $tableClass,
        ?ApiResourceCollectionUrlResolver $urlResolver,
    ): void {
        $this->expectException(NotFoundHttpException::class);

        self::buildRuntime(collectionUrlResolver: $urlResolver)->respond(new $tableClass(), $this->dataTablesRequest());
    }

    #[Test]
    #[DataProvider('provideUnresolvableCollections')]
    public function it_builds_no_provider_for_an_export(
        string $tableClass,
        ?ApiResourceCollectionUrlResolver $urlResolver,
    ): void {
        $call = self::buildRuntime(collectionUrlResolver: $urlResolver)->handle(new $tableClass(), $this->dataTablesRequest());

        $this->expectException(NotFoundHttpException::class);

        $call->dataProvider();
    }

    private function dataTablesRequest(): Request
    {
        return new Request(query: ['draw' => 1, 'start' => 0, 'length' => 10]);
    }

    public static function provideUnresolvableCollections(): iterable
    {
        $urlResolver = new class extends ApiResourceCollectionUrlResolver {
            public function __construct()
            {
            }

            public function resolveCollectionUrl(string $entityClass): ?string
            {
                return null;
            }
        };

        yield 'attribute, no collection operation' => [AttributeApiPlatformUnavailableFixture::class, $urlResolver];

        yield 'attribute, API Platform not installed' => [AttributeApiPlatformUnavailableFixture::class, null];

        yield 'fluent opt-in, no collection operation' => [FluentApiPlatformUnavailableFixture::class, $urlResolver];

        yield 'fluent opt-in, API Platform not installed' => [FluentApiPlatformUnavailableFixture::class, null];
    }
}

abstract class ApiPlatformUnavailableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    /**
     * Reading through Doctrine would skip the authorization the collection operation applies.
     */
    protected function createDataProvider(): ?DataProviderInterface
    {
        throw new \LogicException('An API Platform table without a collection must read no rows.');
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeApiPlatformUnavailableFixture extends ApiPlatformUnavailableFixture
{
}

#[AsDataTable(entityClass: \stdClass::class)]
final class FluentApiPlatformUnavailableFixture extends ApiPlatformUnavailableFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->apiPlatform();
    }
}
