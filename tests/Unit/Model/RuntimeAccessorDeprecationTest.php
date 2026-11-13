<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\FeatureAwareDataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Runtime\DataTableResponder;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class RuntimeAccessorDeprecationTest extends TestCase
{
    use CollectsDeprecations;

    #[Test]
    public function it_deprecates_handle_request(): void
    {
        $table = $this->table();

        $deprecations = $this->deprecationsOf(static fn () => $table->handleRequest(new Request(query: ['draw' => 2])));

        $this->assertDeprecatedWithResponder($deprecations, 'handleRequest');
        $this->assertTrue($table->isDataTableRequestHandled());
    }

    #[Test]
    public function it_deprecates_is_request_handled(): void
    {
        $table = $this->table();
        $table->handleDataTableRequest(new Request(query: ['draw' => 2]));

        $handled      = null;
        $deprecations = $this->deprecationsOf(static function () use ($table, &$handled): void {
            $handled = $table->isRequestHandled();
        });

        $this->assertDeprecatedWithResponder($deprecations, 'isRequestHandled');
        $this->assertTrue($handled);
    }

    #[Test]
    public function it_deprecates_get_response(): void
    {
        $table = $this->table();
        $table->handleDataTableRequest(new Request(query: ['draw' => 2]));

        $response     = null;
        $deprecations = $this->deprecationsOf(static function () use ($table, &$response): void {
            $response = $table->getResponse();
        });

        $this->assertDeprecatedWithResponder($deprecations, 'getResponse');
        $this->assertSame(2, json_decode((string) $response->getContent(), true)['draw']);
    }

    #[Test]
    public function it_deprecates_get_request(): void
    {
        $table = $this->table();
        $table->handleDataTableRequest(new Request(query: ['draw' => 2]));

        $request      = null;
        $deprecations = $this->deprecationsOf(static function () use ($table, &$request): void {
            $request = $table->getRequest();
        });

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('getRequest()', $deprecations[0]);
        $this->assertStringContainsString('use getCurrentRequest() inside the table', $deprecations[0]);
        $this->assertSame(2, $request->draw);
    }

    #[Test]
    public function it_gives_hooks_the_current_request_without_deprecation(): void
    {
        $table = new CurrentRequestTable();

        $deprecations = $this->deprecationsOf(static function () use ($table): void {
            (new DataTableResponder())->respond($table, new Request(query: ['draw' => 7]));
        });

        $this->assertSame(7, $table->seenDraw);
        $this->assertSame([], $this->withoutAuthorizationFallback($deprecations));
    }

    #[Test]
    public function it_deprecates_fetch_data(): void
    {
        $table = $this->table();

        $result       = null;
        $deprecations = $this->deprecationsOf(static function () use ($table, &$result): void {
            $result = $table->fetchData(new DataTableRequest(draw: 1, columns: new Columns([])));
        });

        $this->assertDeprecatedWithoutReplacement($deprecations, 'fetchData');
        $this->assertSame(10, $result->recordsTotal);
    }

    #[Test]
    public function it_deprecates_get_data_provider(): void
    {
        $table = $this->table();

        $provider     = null;
        $deprecations = $this->deprecationsOf(static function () use ($table, &$provider): void {
            $provider = $table->getDataProvider();
        });

        $this->assertDeprecatedWithoutReplacement($deprecations, 'getDataProvider');
        $this->assertNotNull($provider);
    }

    #[Test]
    public function it_raises_no_such_deprecation_on_the_bundle_paths(): void
    {
        $table = $this->table();

        $deprecations = $this->deprecationsOf(static function () use ($table): void {
            (new DataTableResponder())->respond($table, new Request(query: ['draw' => 2]));
            $table->getDataTableRequest();
            $table->getResolvedDataProvider();
            $table->fetchDataTableResult(new DataTableRequest(draw: 1, columns: new Columns([])));
        });

        $this->assertSame([], $this->withoutAuthorizationFallback($deprecations));
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('provideOverridableAccessors')]
    public function it_keeps_calling_an_overridden_accessor_from_the_bundle_paths(string $method): void
    {
        $table = new OverridingAccessorsTable();

        $this->deprecationsOf(static function () use ($table): void {
            (new DataTableResponder())->respond($table, new Request(query: ['draw' => 2]));
            $table->getDataTableRequest();
            $table->fetchDataTableResult(new DataTableRequest(draw: 1, columns: new Columns([])));
        });

        $this->assertContains($method, $table->calls);
    }

    #[Test]
    public function it_deprecates_an_override_once_even_without_calling_parent(): void
    {
        $table = new SilentOverrideTable();

        $deprecations = $this->deprecationsOf(static function () use ($table): void {
            $table->handleDataTableRequest(new Request());
            $table->handleDataTableRequest(new Request());
        });

        $deprecations = array_values(array_filter($this->withoutAuthorizationFallback($deprecations), static fn (string $message): bool => str_contains($message, 'Overriding')));

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString(SilentOverrideTable::class.'::handleRequest()', $deprecations[0]);
        $this->assertStringContainsString('DataTableResponder::respond()', $deprecations[0]);
        $this->assertSame(2, $table->calls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOverridableAccessors(): iterable
    {
        foreach (['handleRequest', 'isRequestHandled', 'getResponse', 'getRequest', 'fetchData'] as $method) {
            yield $method => [$method];
        }
    }

    /**
     * @param list<string> $deprecations
     */
    private function assertDeprecatedWithResponder(array $deprecations, string $method): void
    {
        $deprecations = $this->withoutAuthorizationFallback($deprecations);

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString($method.'()', $deprecations[0]);
        $this->assertStringContainsString('DataTableResponder::respond()', $deprecations[0]);
    }

    /**
     * @param list<string> $deprecations
     */
    private function assertDeprecatedWithoutReplacement(array $deprecations, string $method): void
    {
        $deprecations = $this->withoutAuthorizationFallback($deprecations);

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString($method.'()', $deprecations[0]);
        $this->assertStringContainsString('has no replacement: 2.0 moves it to the bundle runtime. Removed in 2.0.', $deprecations[0]);
    }

    private function table(): ConfigurableDataTable
    {
        return new ConfigurableDataTable([TextColumn::new('id')], dataProvider: new RuntimeAccessorProvider());
    }
}

final class RuntimeAccessorProvider implements DataProviderInterface, FeatureAwareDataProviderInterface
{
    public function supports(DataSourceFeature $feature): bool
    {
        return true;
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return new DataTableResult(recordsTotal: 10, recordsFiltered: 4, data: [['id' => 1]]);
    }
}

final class OverridingAccessorsTable extends AbstractDataTable
{
    /** @var list<string> */
    public array $calls = [];

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function handleRequest(Request $request): static
    {
        $this->calls[] = 'handleRequest';

        return parent::handleRequest($request);
    }

    public function isRequestHandled(): bool
    {
        $this->calls[] = 'isRequestHandled';

        return parent::isRequestHandled();
    }

    public function getResponse(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $this->calls[] = 'getResponse';

        return parent::getResponse();
    }

    public function getRequest(): ?DataTableRequest
    {
        $this->calls[] = 'getRequest';

        return parent::getRequest();
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        $this->calls[] = 'fetchData';

        return parent::fetchData($request);
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return new RuntimeAccessorProvider();
    }
}

final class CurrentRequestTable extends AbstractDataTable
{
    public ?int $seenDraw = null;

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return new CallbackProvider(fn () => $this->mapRow(['id' => 1]));
    }

    protected function mapRow(mixed $row): array
    {
        $this->seenDraw = $this->getCurrentRequest()?->draw;

        return $row;
    }
}

final class CallbackProvider implements DataProviderInterface, FeatureAwareDataProviderInterface
{
    public function __construct(private readonly \Closure $callback)
    {
    }

    public function supports(DataSourceFeature $feature): bool
    {
        return true;
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return new DataTableResult(recordsTotal: 1, recordsFiltered: 1, data: [($this->callback)()]);
    }
}

final class SilentOverrideTable extends AbstractDataTable
{
    public int $calls = 0;

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function handleRequest(Request $request): static
    {
        ++$this->calls;

        return $this;
    }
}
