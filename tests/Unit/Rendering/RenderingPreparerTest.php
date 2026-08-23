<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Rendering;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum RenderingPreparerRole: string implements TranslatableInterface
{
    case Admin = 'admin';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('role.'.$this->value, [], null, $locale);
    }
}

/**
 * @internal
 */
#[CoversClass(RenderingPreparer::class)]
final class RenderingPreparerTest extends TestCase
{
    private const TABLE_CLASS = 'App\\DataTables\\UserDataTable';

    private const TABLE_SERVICE_IDS = [self::TABLE_CLASS => 'app.users_datatable'];

    #[Test]
    public function it_does_nothing_without_resolvers(): void
    {
        $preparer = new RenderingPreparer();
        $table    = new DataTable('Test');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class));

        $this->assertNull($table->getOption('ajax'));
        $this->assertNull($table->getMercureConfig());
    }

    /**
     * @param \Closure(DataTable): DataTable $configure
     * @param array<string, mixed>|null      $expectedAjax
     */
    #[Test]
    #[DataProvider('provideCasesWithoutApiPlatformAjax')]
    public function it_does_not_configure_api_platform_ajax(?AsDataTable $attribute, \Closure $configure, ?array $expectedAjax): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->expects($this->never())->method('resolveCollectionUrl');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = $configure(new DataTable('Test'));

        $preparer->prepare($table, $attribute);

        $this->assertSame($expectedAjax, $table->getOption('ajax'));
        $this->assertNull($table->getOption('apiPlatform'));
    }

    public static function provideCasesWithoutApiPlatformAjax(): iterable
    {
        yield 'without attribute' => [null, self::unchanged(), null];

        yield 'attribute without opt in' => [new AsDataTable(entityClass: \stdClass::class), self::unchanged(), null];

        yield 'ajax already set' => [
            new AsDataTable(entityClass: \stdClass::class),
            static fn (DataTable $table): DataTable => $table->ajax('/custom-url'),
            ['type' => 'GET', 'url' => '/custom-url'],
        ];

        yield 'data already set' => [
            new AsDataTable(entityClass: \stdClass::class),
            static fn (DataTable $table): DataTable => $table->data([['id' => 1]]),
            null,
        ];
    }

    /**
     * @param \Closure(DataTable): DataTable $configure
     */
    #[Test]
    #[DataProvider('provideApiPlatformOptIns')]
    public function it_configures_api_platform_ajax(AsDataTable $attribute, \Closure $configure): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/products');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = $configure(new DataTable('Test'));

        $preparer->prepare($table, $attribute);

        $this->assertSame(['type' => 'GET', 'url' => '/api/products'], $table->getOption('ajax'));
        $this->assertTrue($table->getOption('apiPlatform'));
    }

    public static function provideApiPlatformOptIns(): iterable
    {
        yield 'opted in through the attribute' => [
            new AsDataTable(entityClass: \stdClass::class, apiPlatform: true),
            self::unchanged(),
        ];

        yield 'opted in through configureDataTable()' => [
            new AsDataTable(entityClass: \stdClass::class),
            static fn (DataTable $table): DataTable => $table->apiPlatform(true),
        ];
    }

    /**
     * @param list<\Pentiminax\UX\DataTables\Contracts\ColumnInterface> $columns
     */
    #[Test]
    #[DataProvider('provideColumnsForTemplateRendering')]
    public function it_configures_api_platform_template_rendering_only_for_template_columns(array $columns, bool $expectsTemplateRendering): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/users');

        $registry = $this->createAjaxRegistry(self::TABLE_SERVICE_IDS);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($expectsTemplateRendering ? $this->once() : $this->never())
            ->method('generate')
            ->with('ux_datatables_ajax_templates')
            ->willReturn('/datatables/ajax/templates');

        $preparer = new RenderingPreparer(
            urlResolver: $urlResolver,
            urlGenerator: $urlGenerator,
            ajaxRegistry: $registry,
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->columns($columns);

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));

        $expected = $expectsTemplateRendering ? [
            'url'   => '/datatables/ajax/templates',
            'table' => $registry->getToken(self::TABLE_CLASS),
        ] : null;

        $this->assertSame($expected, $table->getOption('apiPlatformTemplateRendering'));
    }

    public static function provideColumnsForTemplateRendering(): iterable
    {
        yield 'with a template column' => [
            [
                TemplateColumn::new('avatar', 'Avatar')->setTemplate('user.html.twig'),
                TextColumn::new('email', 'Email'),
            ],
            true,
        ];

        yield 'without a template column' => [[TextColumn::new('email', 'Email')], false];
    }

    #[Test]
    public function it_skips_ajax_when_collection_url_is_null(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->method('resolveCollectionUrl')->willReturn(null);

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = new DataTable('Test');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));

        $this->assertNull($table->getOption('ajax'));
    }

    #[Test]
    public function it_configures_mercure(): void
    {
        $mercureConfig = (new MercureConfig(topics: ['/products/{id}']))
            ->withHubUrl('/.well-known/mercure');

        $mercureResolver = $this->createMock(MercureConfigResolverInterface::class);
        $mercureResolver->method('resolveMercureConfig')
            ->with(\stdClass::class)
            ->willReturn($mercureConfig);

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = new DataTable('Test');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        $this->assertNotNull($table->getMercureConfig());
        $this->assertSame('/.well-known/mercure', $table->getMercureConfig()->hubUrl);
    }

    #[Test]
    public function it_resolves_mercure_config_without_mutating_the_table(): void
    {
        $mercureConfig = (new MercureConfig(topics: ['/products/{id}']))
            ->withHubUrl('/.well-known/mercure');

        $mercureResolver = $this->createMock(MercureConfigResolverInterface::class);
        $mercureResolver->method('resolveMercureConfig')
            ->with(\stdClass::class)
            ->willReturn($mercureConfig);

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = new DataTable('Test');

        $resolved = $preparer->resolveMercureConfig($table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        // The pure resolver returns the config the render path would serialize
        // but must never write it back onto the (container-shared) table — that
        // is configureMercure()'s job. This is what lets the server-side publish
        // path reuse it during a mutation without polluting the shared instance.
        $this->assertSame($mercureConfig, $resolved);
        $this->assertNull($table->getMercureConfig());
    }

    #[Test]
    public function it_configures_explicit_mercure_topics_from_attribute(): void
    {
        $mercureResolver = $this->createMock(MercureConfigResolverInterface::class);
        $mercureResolver->expects($this->never())->method('resolveMercureConfig');

        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('/.well-known/mercure');

        $preparer = new RenderingPreparer(
            mercureResolver: $mercureResolver,
            mercureHubUrlResolver: $hubUrlResolver,
        );
        $table = (new DataTable('Test'))->ajax('/api/books');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, mercure: [
            'topics'          => ['https://example.com/books'],
            'withCredentials' => true,
            'debounceMs'      => 250,
        ]));

        $this->assertSame([
            'hubUrl'          => '/.well-known/mercure',
            'topics'          => ['https://example.com/books'],
            'withCredentials' => true,
            'debounceMs'      => 250,
        ], $table->getOptions()['mercure']);
    }

    /**
     * @param \Closure(DataTable): DataTable $configure
     */
    #[Test]
    #[DataProvider('provideCasesSkippingMercure')]
    public function it_skips_mercure_without_consulting_the_resolver(AsDataTable $attribute, \Closure $configure): void
    {
        $mercureResolver = $this->createMock(MercureConfigResolverInterface::class);
        $mercureResolver->expects($this->never())->method('resolveMercureConfig');

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = $configure(new DataTable('Test'));

        $preparer->prepare($table, $attribute);

        $this->assertNull($table->getMercureConfig());
    }

    public static function provideCasesSkippingMercure(): iterable
    {
        yield 'attribute mercure is false' => [
            new AsDataTable(entityClass: \stdClass::class, mercure: false),
            self::unchanged(),
        ];

        yield 'client side data without ajax' => [
            new AsDataTable(entityClass: \stdClass::class, mercure: true),
            static fn (DataTable $table): DataTable => $table->data([['id' => 1]]),
        ];
    }

    #[Test]
    public function it_enriches_manual_mercure_config_with_resolved_hub_url(): void
    {
        $mercureResolver = $this->createMock(MercureConfigResolverInterface::class);
        $mercureResolver->expects($this->never())->method('resolveMercureConfig');

        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('/.well-known/mercure');

        $preparer = new RenderingPreparer(
            mercureResolver: $mercureResolver,
            mercureHubUrlResolver: $hubUrlResolver,
        );
        $table = new DataTable('Test');
        $table->mercure(topics: ['/existing']);

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        $this->assertSame('/.well-known/mercure', $table->getMercureConfig()->hubUrl);
        $this->assertSame(['/existing'], $table->getMercureConfig()->topics);
    }

    #[Test]
    public function it_throws_when_manual_mercure_has_no_resolvable_hub_url(): void
    {
        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn(null);

        $preparer = new RenderingPreparer(mercureHubUrlResolver: $hubUrlResolver);
        $table    = new DataTable('Test');
        $table->mercure(topics: ['/existing']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Mercure hub URL could not be resolved');

        $preparer->prepare($table, null);
    }

    #[Test]
    public function it_skips_mercure_when_resolver_returns_null(): void
    {
        $mercureResolver = $this->createMock(MercureConfigResolverInterface::class);
        $mercureResolver->method('resolveMercureConfig')->willReturn(null);

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = new DataTable('Test');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        $this->assertNull($table->getMercureConfig());
    }

    #[Test]
    public function it_auto_configures_ajax_for_server_side_table_without_explicit_url(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->with(RenderingPreparer::AJAX_DATA_ROUTE)
            ->willReturn('/datatables/ajax/data');

        $registry = $this->createAjaxRegistry(self::TABLE_SERVICE_IDS);
        $preparer = new RenderingPreparer(urlGenerator: $urlGenerator, ajaxRegistry: $registry);
        $table    = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->serverSide();

        $preparer->prepare($table, null);

        $ajax = $table->getOption('ajax');
        $this->assertIsArray($ajax);
        $this->assertSame('/datatables/ajax/data', $ajax['url']);
        $this->assertSame('GET', $ajax['type']);
        $this->assertSame(['table' => $registry->getToken(self::TABLE_CLASS)], $ajax['data']);
        $this->assertStringNotContainsString('UserDataTable', $ajax['data']['table']);
    }

    /**
     * @param \Closure(DataTable): DataTable $configure
     * @param array<string, mixed>|null      $expectedAjax
     */
    #[Test]
    #[DataProvider('provideCasesWithoutAutoConfiguredAjax')]
    public function it_does_not_auto_configure_ajax(bool $withUrlGenerator, bool $withRegistry, \Closure $configure, ?array $expectedAjax): void
    {
        $urlGenerator = null;
        if ($withUrlGenerator) {
            $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
            $urlGenerator->expects($this->never())->method('generate');
        }

        $preparer = new RenderingPreparer(
            urlGenerator: $urlGenerator,
            ajaxRegistry: $withRegistry ? $this->createAjaxRegistry(self::TABLE_SERVICE_IDS) : null,
        );
        $table = $configure(new DataTable('Test'));

        $preparer->prepare($table, null);

        $this->assertSame($expectedAjax, $table->getOption('ajax'));
    }

    public static function provideCasesWithoutAutoConfiguredAjax(): iterable
    {
        yield 'client side table' => [
            true,
            true,
            static fn (DataTable $table): DataTable => $table->setDataTableClass(self::TABLE_CLASS),
            null,
        ];

        yield 'manual ajax url' => [
            true,
            true,
            static fn (DataTable $table): DataTable => $table
                ->setDataTableClass(self::TABLE_CLASS)
                ->serverSide()
                ->ajax('/custom-endpoint'),
            ['type' => 'GET', 'url' => '/custom-endpoint'],
        ];

        yield 'missing url generator' => [
            false,
            true,
            static fn (DataTable $table): DataTable => $table
                ->setDataTableClass(self::TABLE_CLASS)
                ->serverSide(),
            null,
        ];

        yield 'missing ajax registry' => [
            true,
            false,
            static fn (DataTable $table): DataTable => $table
                ->setDataTableClass(self::TABLE_CLASS)
                ->serverSide(),
            null,
        ];

        yield 'missing data table class' => [
            true,
            true,
            static fn (DataTable $table): DataTable => $table->serverSide(),
            null,
        ];
    }

    #[Test]
    public function it_does_not_auto_configure_ajax_when_api_platform_is_enabled(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->method('resolveCollectionUrl')->willReturn('/api/users');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver, urlGenerator: $urlGenerator);
        $table    = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->serverSide();

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));

        $this->assertSame('/api/users', $table->getOption('ajax')['url']);
    }

    /**
     * @param array<string, string>|null $query                 null when there is no current request
     * @param list<string>               $forwarded
     * @param array<string, string>      $expectedForwardedData
     */
    #[Test]
    #[DataProvider('provideForwardedQueryParameters')]
    public function it_forwards_only_present_query_parameters_into_auto_ajax_data(?array $query, array $forwarded, array $expectedForwardedData): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/datatables/ajax/data');

        $registry = $this->createAjaxRegistry(self::TABLE_SERVICE_IDS);
        $preparer = new RenderingPreparer(
            urlGenerator: $urlGenerator,
            ajaxRegistry: $registry,
            requestStack: null === $query ? new RequestStack() : $this->createRequestStack($query),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->serverSide()
            ->forwardQueryParameters($forwarded);

        $preparer->prepare($table, null);

        $this->assertSame([
            'type' => 'GET',
            'url'  => '/datatables/ajax/data',
            'data' => array_merge(['table' => $registry->getToken(self::TABLE_CLASS)], $expectedForwardedData),
        ], $table->getOption('ajax'));
    }

    public static function provideForwardedQueryParameters(): iterable
    {
        yield 'unrelated parameters stay out of the payload' => [
            ['q' => 'foo', 'pending' => '1', 'unrelated' => 'x'],
            ['q', 'pending'],
            ['q' => 'foo', 'pending' => '1'],
        ];

        yield 'parameters absent from the request are skipped' => [
            ['q' => 'foo'],
            ['q', 'pending'],
            ['q' => 'foo'],
        ];

        yield 'without a current request' => [null, ['q'], []];
    }

    #[Test]
    public function it_forwards_query_parameters_into_manual_ajax(): void
    {
        $preparer = new RenderingPreparer(
            requestStack: $this->createRequestStack(['q' => 'foo']),
        );
        $table = (new DataTable('Test'))
            ->ajax('/custom-endpoint')
            ->forwardQueryParameters(['q']);

        $preparer->prepare($table, null);

        $ajax = $table->getOption('ajax');
        $this->assertSame('/custom-endpoint', $ajax['url']);
        $this->assertSame(['q' => 'foo'], $ajax['data']);
    }

    #[Test]
    public function it_translates_column_titles_without_manual_resynchronization(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('Status')
            ->willReturn('Statut');

        $preparer = new RenderingPreparer(translator: $translator);
        $table    = (new DataTable('Test'))->columns([
            TextColumn::new('status', 'Status'),
        ]);

        $preparer->prepare($table, null);

        $this->assertSame('Statut', $table->getColumns()['status']->getTitle());
        $this->assertSame('Statut', $table->getOptions()['columns'][0]['title']);
    }

    #[Test]
    public function it_translates_translatable_filter_option_labels(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnMap([
                ['role.admin', [], null, null, 'Administrateur'],
                ['filter.bar.title', [], FilterLabels::DOMAIN, null, 'Filtres'],
                ['filter.bar.reset', [], FilterLabels::DOMAIN, null, 'Réinitialiser'],
                ['filter.bar.apply', [], FilterLabels::DOMAIN, null, 'Appliquer les filtres'],
                ['filter.bar.all', [], FilterLabels::DOMAIN, null, 'Tous'],
            ]);

        $filters = (new Filters())->add(
            ChoiceFilter::new('role')->options(RenderingPreparerRole::class),
        );
        $table = (new DataTable('Test'))->setFilters($filters);

        $preparer = new RenderingPreparer(translator: $translator);
        $preparer->prepare($table, null);

        $this->assertSame(
            ['admin' => 'Administrateur'],
            $table->getOptions()['filters'][0]['options'],
        );
    }

    /**
     * @param array<string, string>   $labelOverrides
     * @param list<array<int, mixed>> $translationMap
     * @param array<string, string>   $expected
     */
    #[Test]
    #[DataProvider('provideFilterBarLabels')]
    public function it_translates_filter_bar_labels(array $labelOverrides, array $translationMap, array $expected): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap($translationMap);

        $filters = new Filters();
        if ([] !== $labelOverrides) {
            $filters->labels(...$labelOverrides);
        }
        $filters->add(TextFilter::new('name'));

        $table = (new DataTable('Test'))->setFilters($filters);

        (new RenderingPreparer(translator: $translator))->prepare($table, null);

        $this->assertSame($expected, $table->getOptions()['filterLabels']);
    }

    public static function provideFilterBarLabels(): iterable
    {
        yield 'developer overrides fall back to the bundle defaults for untouched labels' => [
            ['title' => 'filter.title', 'apply' => 'filter.apply'],
            [
                // Developer overrides are translated in the default domain.
                ['filter.title', [], null, null, 'Filtres'],
                ['filter.apply', [], null, null, 'Appliquer'],
                // Untouched labels fall back to the bundle defaults (DataTables domain).
                ['filter.bar.reset', [], FilterLabels::DOMAIN, null, 'Réinitialiser'],
                ['filter.bar.all', [], FilterLabels::DOMAIN, null, 'Tous'],
            ],
            [
                'title' => 'Filtres',
                'reset' => 'Réinitialiser',
                'apply' => 'Appliquer',
                'all'   => 'Tous',
            ],
        ];

        yield 'localized defaults without overrides' => [
            [],
            [
                ['filter.bar.title', [], FilterLabels::DOMAIN, null, 'Filtres'],
                ['filter.bar.reset', [], FilterLabels::DOMAIN, null, 'Réinitialiser'],
                ['filter.bar.apply', [], FilterLabels::DOMAIN, null, 'Appliquer les filtres'],
                ['filter.bar.all', [], FilterLabels::DOMAIN, null, 'Tous'],
            ],
            [
                'title' => 'Filtres',
                'reset' => 'Réinitialiser',
                'apply' => 'Appliquer les filtres',
                'all'   => 'Tous',
            ],
        ];
    }

    /**
     * @return \Closure(DataTable): DataTable
     */
    private static function unchanged(): \Closure
    {
        return static fn (DataTable $table): DataTable => $table;
    }

    /**
     * @param array<string, string> $query
     */
    private function createRequestStack(array $query): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(new Request($query));

        return $stack;
    }

    /**
     * @param array<string, string> $serviceIdsByClass
     */
    private function createAjaxRegistry(array $serviceIdsByClass): AjaxDataTableRegistry
    {
        return new AjaxDataTableRegistry(
            new class implements ContainerInterface {
                public function get(string $id): mixed
                {
                    throw new \LogicException('The test registry should only generate tokens.');
                }

                public function has(string $id): bool
                {
                    return false;
                }
            },
            new AjaxDataTableTokenManager('test-secret'),
            $serviceIdsByClass,
        );
    }
}
