<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ColumnAutoDetector;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataSourceMode;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
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
final class AbstractDataTableOptInsTest extends TestCase
{
    use CollectsDeprecations;

    /**
     * @param class-string<OptInFixture> $attribute
     * @param class-string<OptInFixture> $fluent
     */
    #[Test]
    #[DataProvider('provideDeclarationSurfaces')]
    public function it_renders_the_same_payload_whichever_surface_declared_the_opt_in(string $attribute, string $fluent): void
    {
        $fromAttribute = $this->render($attribute, '/api/books');
        $fromFluent    = $this->render($fluent, '/api/books');

        $this->assertSame($fromFluent, $fromAttribute);
        $this->assertTrue($fromAttribute['apiPlatform']);
    }

    public static function provideDeclarationSurfaces(): iterable
    {
        yield 'bundle collection url' => [AttributeOptInFixture::class, FluentOptInFixture::class];
    }

    #[Test]
    public function it_keeps_the_1_5_payload_of_an_ajax_table_and_deprecates_the_attribute_only_opt_in(): void
    {
        $payload = [];
        $notices = $this->deprecationsOf(function () use (&$payload): void {
            $payload = $this->render(AttributeAjaxOptInFixture::class, '/api/books');
        });

        $this->assertNotTrue($payload['apiPlatform'] ?? null);
        $this->assertCount(1, $this->matching($notices, '2.0 sends the API Platform flag'));
        $this->assertNotEmpty($this->matching($notices, 'apiPlatform(false)'));
    }

    #[Test]
    public function it_sends_the_flag_of_an_ajax_table_that_opts_in_fluently_without_a_notice(): void
    {
        $payload = [];
        $notices = $this->deprecationsOf(function () use (&$payload): void {
            $payload = $this->render(FluentAjaxOptInFixture::class, '/api/books');
        });

        $this->assertTrue($payload['apiPlatform']);
        $this->assertSame([], $this->matching($notices, '2.0 sends the API Platform flag'));
    }

    /**
     * @param class-string<OptInFixture> $tableClass
     * @param array<string, mixed>       $source
     */
    #[Test]
    #[DataProvider('provideFluentOptOutsOverPayloadSources')]
    public function it_keeps_the_fluent_opt_out_in_the_payload_and_deprecates_what_the_server_still_reads(string $tableClass, array $source, string $notice): void
    {
        $detector = $this->createStub(ColumnAutoDetector::class);
        $detector->method('supports')->willReturn(true);
        $detector->method('detectColumns')->willReturn([TextColumn::new('detected')]);

        $payload = [];
        $notices = $this->deprecationsOf(function () use (&$payload, $tableClass, $detector): void {
            $payload = $this->render($tableClass, '/api/books', $detector);
        });

        $this->assertFalse($payload['apiPlatform']);
        foreach ($source as $option => $value) {
            $this->assertSame($value, $payload[$option]);
        }
        $this->assertSame([\sprintf($notice, $tableClass)], $this->apiPlatformNotices($notices));
    }

    public static function provideFluentOptOutsOverPayloadSources(): iterable
    {
        $autoDetected = 'Since pentiminax/ux-datatables 1.6: Table "%s" opts into API Platform on #[AsDataTable] and out through apiPlatform(false): the payload already follows apiPlatform(false), but the server still auto-detects its columns from the API Platform resource and reads the rows of exports, bulk and row actions through the API Platform provider. In 2.0 apiPlatform(false) wins there too: the table gets no auto-detected columns, and those reads go through Doctrine. Declare the columns in configureColumns() and remove the attribute flag.';
        $explicit     = 'Since pentiminax/ux-datatables 1.6: Table "%s" opts into API Platform on #[AsDataTable] and out through apiPlatform(false): the payload already follows apiPlatform(false), but the server still reads the rows of exports, bulk and row actions through the API Platform provider. In 2.0 apiPlatform(false) wins there too, and those reads go through Doctrine. Remove the attribute flag.';

        yield 'data(), auto-detected columns' => [AttributeInlineFluentOptOutAutoDetectedFixture::class, ['data' => [['id' => 1]]], $autoDetected];

        yield 'data(), explicit columns' => [AttributeInlineFluentOptOutFixture::class, ['data' => [['id' => 1]]], $explicit];

        yield 'ajax(), auto-detected columns' => [AttributeAjaxFluentOptOutAutoDetectedFixture::class, [], $autoDetected];

        yield 'ajax(), explicit columns' => [AttributeAjaxFluentOptOutFixture::class, [], $explicit];
    }

    #[Test]
    public function it_records_the_source_mode_before_api_platform_adds_ajax_and_clears_it_on_reset(): void
    {
        $resolver = new class extends ApiResourceCollectionUrlResolver {
            public function __construct()
            {
            }

            public function resolveCollectionUrl(string $entityClass): ?string
            {
                return '/api/books';
            }
        };
        $table = new FluentOptInFixture($resolver);
        $table->prepareDataTableForRendering();

        $this->assertSame(DataSourceMode::ApiPlatform, $table->getRenderedDataSourceMode());
        $this->assertNotNull($table->getConfiguredDataTable()->getOption('ajax'));
        $table->resetDataTableState();
        $this->assertNull($table->getRenderedDataSourceMode());
    }

    #[Test]
    public function it_calls_a_prepare_for_rendering_override_from_get_data_table(): void
    {
        $table = new class(null) extends FluentOptInFixture {
            public int $calls = 0;

            public function prepareForRendering(): void
            {
                ++$this->calls;
                parent::prepareForRendering();
            }
        };

        $this->deprecationsOf(static fn () => $table->getDataTable());

        $this->assertSame(1, $table->calls);
    }

    #[Test]
    public function it_reads_the_configuration_changed_after_initialization_when_rendering(): void
    {
        $table = new FluentOptInFixture(null);
        $table->getConfiguredDataTable()->editModalTemplate('custom/modal.html.twig');

        $payload = $table->prepareDataTableForRendering();

        $this->assertSame('custom/modal.html.twig', $payload->getEditModalTemplate());
        $this->assertSame('custom/modal.html.twig', $table->getResolvedOptIns()->editModalTemplate);
    }

    #[Test]
    #[DataProvider('provideInlineSurfaces')]
    public function it_withdraws_the_flag_from_an_inline_table_and_deprecates_the_combination(string $tableClass): void
    {
        $payload = [];
        $notices = $this->deprecationsOf(function () use (&$payload, $tableClass): void {
            $payload = $this->render($tableClass, '/api/books');
        });

        $this->assertNotTrue($payload['apiPlatform'] ?? null);
        $this->assertSame([['id' => 1]], $payload['data']);
        $this->assertNotEmpty($this->matching($notices, 'data()'));
    }

    public static function provideInlineSurfaces(): iterable
    {
        yield 'attribute' => [AttributeInlineOptInFixture::class];

        yield 'fluent' => [FluentInlineOptInFixture::class];
    }

    #[Test]
    #[DataProvider('provideOptedInSurfaces')]
    public function it_deprecates_an_opt_in_without_a_collection(string $tableClass): void
    {
        $notices = $this->deprecationsOf(function () use ($tableClass): void {
            $this->render($tableClass, null);
        });

        $this->assertCount(1, $this->matching($notices, 'no collection'));
    }

    public static function provideOptedInSurfaces(): iterable
    {
        yield 'attribute' => [AttributeOptInFixture::class];

        yield 'fluent' => [FluentOptInFixture::class];
    }

    #[Test]
    public function it_deprecates_a_fluent_opt_out_the_attribute_overrides(): void
    {
        $payload = [];
        $notices = $this->deprecationsOf(function () use (&$payload): void {
            $payload = $this->render(AttributeOptInFluentOptOutFixture::class, '/api/books');
        });

        $this->assertSame([\sprintf('Since pentiminax/ux-datatables 1.6: Table "%s" opts into API Platform on #[AsDataTable] and out through apiPlatform(false): the attribute wins today, the fluent value will win in 2.0. Remove one of them.', AttributeOptInFluentOptOutFixture::class)], $this->apiPlatformNotices($notices));
        $this->assertTrue($payload['apiPlatform'], 'The 1.x precedence is unchanged in 1.6.');
    }

    #[Test]
    #[DataProvider('provideOptedInSurfaces')]
    public function it_answers_not_found_after_rendering_whichever_surface_opted_in(string $tableClass): void
    {
        $table = new $tableClass(null);

        $this->deprecationsOf(static fn () => $table->getDataTable());
        $table->handleDataTableRequest(new Request(query: ['draw' => 1, 'start' => 0, 'length' => 10]));

        $this->expectException(NotFoundHttpException::class);

        $table->createDataTableResponse();
    }

    /**
     * @param list<string> $notices
     *
     * @return list<string>
     */
    private function matching(array $notices, string $needle): array
    {
        return array_values(array_filter($notices, static fn (string $notice): bool => str_contains($notice, $needle)));
    }

    /**
     * @param list<string> $notices
     *
     * @return list<string>
     */
    private function apiPlatformNotices(array $notices): array
    {
        return array_values(array_filter($notices, static fn (string $notice): bool => str_contains($notice, 'API Platform') || str_contains($notice, 'apiPlatform')));
    }

    /**
     * @return array<string, mixed>
     */
    private function render(string $tableClass, ?string $collectionUrl, ?ColumnAutoDetector $detector = null): array
    {
        $urlResolver = new class($collectionUrl) extends ApiResourceCollectionUrlResolver {
            public function __construct(private readonly ?string $url)
            {
            }

            public function resolveCollectionUrl(string $entityClass): ?string
            {
                return $this->url;
            }
        };

        $options = (new $tableClass($urlResolver, $detector))->getDataTable()->getOptions();
        unset($options['columns']);

        return $options;
    }
}

abstract class OptInFixture extends AbstractDataTable
{
    public function __construct(?ApiResourceCollectionUrlResolver $urlResolver, ?ColumnAutoDetector $detector = null)
    {
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            columnResolver: new ColumnResolver(columnAutoDetector: $detector),
            renderingPreparer: new RenderingPreparer(urlResolver: $urlResolver),
        ));
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeOptInFixture extends OptInFixture
{
}

#[AsDataTable(entityClass: \stdClass::class)]
class FluentOptInFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->apiPlatform();
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeAjaxOptInFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->ajax('/hydra/books');
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
final class FluentAjaxOptInFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->ajax('/hydra/books')->apiPlatform();
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeAjaxFluentOptOutFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->ajax('/plain')->apiPlatform(false);
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeInlineOptInFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->data([['id' => 1]]);
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
final class FluentInlineOptInFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->data([['id' => 1]])->apiPlatform();
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeOptInFluentOptOutFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->apiPlatform(false);
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeInlineFluentOptOutFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->data([['id' => 1]])->apiPlatform(false);
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeInlineFluentOptOutAutoDetectedFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->data([['id' => 1]])->apiPlatform(false);
    }

    public function configureColumns(): iterable
    {
        return AbstractDataTable::configureColumns();
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeAjaxFluentOptOutAutoDetectedFixture extends OptInFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->ajax('/plain')->apiPlatform(false);
    }

    public function configureColumns(): iterable
    {
        return AbstractDataTable::configureColumns();
    }
}
