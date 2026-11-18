<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\NumberRangeFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Model\Summary\Sum;
use Pentiminax\UX\DataTables\Runtime\PreparedDataTable;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use Pentiminax\UX\DataTables\Runtime\SearchListOptionsResolver;
use Pentiminax\UX\DataTables\Tests\Support\BuildsAjaxRegistry;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use Pentiminax\UX\DataTables\Tests\Support\PreparesPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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

enum RenderingPreparerSearchListStatus: string implements TranslatableInterface
{
    case Draft = 'draft';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('status.'.$this->value, [], null, $locale);
    }
}

/**
 * @internal
 */
#[CoversClass(RenderingPreparer::class)]
final class RenderingPreparerTest extends TestCase
{
    use PreparesPayload;
    use CollectsDeprecations;
    use BuildsAjaxRegistry;

    private const TABLE_CLASS = 'App\\DataTables\\UserDataTable';

    private const TABLE_SERVICE_IDS = [self::TABLE_CLASS => 'app.users_datatable'];

    #[Test]
    public function it_does_nothing_without_resolvers(): void
    {
        $preparer = new RenderingPreparer();
        $table    = new DataTable('Test');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class));

        $this->assertNull(self::optionOf($tablePayload, 'ajax'));
        $this->assertNull($tablePayload->mercure);
    }

    #[Test]
    public function it_rejects_an_option_and_an_extension_sharing_a_payload_key(): void
    {
        $table = (new DataTable('t', ['select' => true]))->addExtension(new SelectExtension());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The payload key "select" of table "t" is set twice. Rename the option or the extension that sets it.');

        self::payloadOf($table);
    }

    #[Test]
    public function it_rejects_polling_on_a_table_that_embeds_its_rows(): void
    {
        $table = (new DataTable('Test'))->poll('10s');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Polling requires an Ajax source, but "Test" has no ajax option once prepared.');

        $tablePayload = self::prepare(new RenderingPreparer(), $table, null);
    }

    #[Test]
    public function it_accepts_polling_on_an_ajax_table(): void
    {
        $table = (new DataTable('Test'))->ajax('/rows')->poll('10s');

        $tablePayload = self::prepare(new RenderingPreparer(), $table, null);

        $this->assertSame(10, self::optionOf($tablePayload, 'pollInterval'));
    }

    #[Test]
    public function it_prepares_translated_static_search_list_options(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['status.draft', [], null, null, 'Brouillon'],
            ['status', [], null, null, 'status'],
        ]);
        $searchList = SearchList::new()->options(RenderingPreparerSearchListStatus::class);
        $table      = (new DataTable('orders'))
            ->columns([TextColumn::new('status')])
            ->addExtension((new ColumnControlExtension([]))->add(1, [$searchList]));
        $preparer = new RenderingPreparer(
            translator: $translator,
            searchListOptionsResolver: new SearchListOptionsResolver($translator),
        );

        $tablePayload = self::prepare($preparer, $table, null);

        $this->assertSame([
            'extend'  => 'searchList',
            'options' => [['label' => 'Brouillon', 'value' => 'draft']],
        ], $tablePayload->getView()['columnControl'][0]['content'][0]);
        $this->assertSame('Draft', $searchList->jsonSerialize()['options'][0]['label']);
    }

    /**
     * @param \Closure(DataTable): DataTable $configure
     * @param array<string, mixed>|null      $expectedAjax
     */
    #[Test]
    #[DataProvider('provideCasesWithoutApiPlatformAjax')]
    public function it_does_not_configure_api_platform_ajax(?AsDataTable $attribute, \Closure $configure, ?array $expectedAjax): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolver::class);
        $urlResolver->expects($this->never())->method('resolveCollectionUrl');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = $configure(new DataTable('Test'));

        $tablePayload = self::prepare($preparer, $table, $attribute);

        $this->assertSame($expectedAjax, self::optionOf($tablePayload, 'ajax'));
        $this->assertNull(self::optionOf($tablePayload, 'apiPlatform'));
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
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolver::class);
        $urlResolver->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/products');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = $configure(new DataTable('Test'));

        $tablePayload = self::prepare($preparer, $table, $attribute);

        $this->assertSame(['type' => 'GET', 'url' => '/api/products'], self::optionOf($tablePayload, 'ajax'));
        $this->assertTrue(self::optionOf($tablePayload, 'apiPlatform'));
    }

    #[Test]
    public function it_rejects_summaries_on_an_api_platform_table(): void
    {
        $urlResolver = $this->createStub(ApiResourceCollectionUrlResolver::class);
        $urlResolver->method('resolveCollectionUrl')->willReturn('/api/products');

        $table = (new DataTable('Test'))->columns([NumberColumn::new('price')->summarize(new Sum())]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Column summaries are not supported on an API Platform collection');

        $tablePayload = self::prepare(new RenderingPreparer(urlResolver: $urlResolver), $table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));
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
     * @param list<ColumnInterface> $columns
     */
    #[Test]
    #[DataProvider('provideColumnsForServerSideReading')]
    public function it_reads_the_collection_server_side_only_for_entity_dependent_columns(array $columns, bool $expectsServerSide): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolver::class);
        $urlResolver->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/users');

        $registry = $this->createAjaxRegistry(self::TABLE_SERVICE_IDS);

        $generated    = [];
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static function (string $route) use (&$generated): string {
            $generated[] = $route;

            return '/datatables/ajax/data';
        });

        $preparer = new RenderingPreparer(
            urlResolver: $urlResolver,
            urlGenerator: $urlGenerator,
            ajaxRegistry: $registry,
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->columns($columns);

        $tablePayload = null;
        $deprecations = $this->deprecationsOf(static function () use ($preparer, $table, &$tablePayload): void {
            $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));
        });

        $this->assertSame([], $this->withoutAuthorizationFallback($deprecations));
        $this->assertCount($expectsServerSide ? 1 : 0, array_keys($generated, 'ux_datatables_ajax_data', true));
        $this->assertTrue(self::optionOf($tablePayload, 'apiPlatform'));

        if (!$expectsServerSide) {
            $this->assertNull(self::optionOf($tablePayload, 'apiPlatformServerSide'));
            $this->assertSame(['type' => 'GET', 'url' => '/api/users'], self::optionOf($tablePayload, 'ajax'));

            return;
        }

        $this->assertTrue(self::optionOf($tablePayload, 'apiPlatformServerSide'));
        $this->assertTrue(self::optionOf($tablePayload, 'serverSide'));
        $this->assertSame([
            'type' => 'GET',
            'url'  => '/datatables/ajax/data',
            'data' => ['table' => $registry->getToken(self::TABLE_CLASS)],
        ], self::optionOf($tablePayload, 'ajax'));
    }

    public static function provideColumnsForServerSideReading(): iterable
    {
        yield 'with a template column' => [
            [
                TemplateColumn::new('avatar', 'Avatar')->template('user.html.twig'),
                TextColumn::new('email', 'Email'),
            ],
            true,
        ];

        yield 'with an action column' => [
            [
                TextColumn::new('email', 'Email'),
                ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete())),
            ],
            true,
        ];

        yield 'with a resolved url column' => [
            [UrlColumn::new('profile', 'Profile')->linkToRoute('app_user_show', ['id' => 'id'])],
            true,
        ];

        yield 'with a url column carrying no url' => [[UrlColumn::new('profile', 'Profile')], false];

        yield 'without an entity dependent column' => [[TextColumn::new('email', 'Email')], false];
    }

    #[Test]
    public function it_treats_a_blank_fluent_edit_modal_value_as_unset(): void
    {
        $table = (new DataTable('Test'))->editModalTemplate(' ')->editModalAdapter('');

        $preparer     = new RenderingPreparer();
        $optIns       = $preparer->resolveOptIns($table, new AsDataTable(entityClass: \stdClass::class, editModalTemplate: 'attribute.html.twig', editModalAdapter: 'bootstrap'));
        $tablePayload = self::prepareWith($preparer, $table, $optIns);

        $this->assertSame('attribute.html.twig', $optIns->editModalTemplate);
        $this->assertSame('bootstrap', $tablePayload->editModalAdapter);
    }

    #[Test]
    public function it_leaves_a_blank_edit_modal_value_unset_without_an_attribute_value(): void
    {
        $table = (new DataTable('Test'))->editModalAdapter('');

        $tablePayload = self::prepare(new RenderingPreparer(), $table, new AsDataTable(entityClass: \stdClass::class));

        $this->assertNull($tablePayload->editModalAdapter);
    }

    #[Test]
    public function it_refuses_an_api_platform_opt_in_when_collection_url_is_null(): void
    {
        $urlResolver = $this->createStub(ApiResourceCollectionUrlResolver::class);
        $urlResolver->method('resolveCollectionUrl')->willReturn(null);

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Table "Test" opts into API Platform but "stdClass" exposes no collection operation');

        self::prepare($preparer, new DataTable('Test'), new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));
    }

    #[Test]
    public function it_configures_mercure(): void
    {
        $mercureConfig = (new MercureConfig(topics: ['/products/{id}']))
            ->withHubUrl('/.well-known/mercure');

        $mercureResolver = $this->createMock(MercureConfigResolver::class);
        $mercureResolver->method('resolveMercureConfig')
            ->with(\stdClass::class)
            ->willReturn($mercureConfig);

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = (new DataTable('Test'))->serverSide();

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        $this->assertNotNull($tablePayload->mercure);
        $this->assertSame('/.well-known/mercure', $tablePayload->mercure->hubUrl);
    }

    #[Test]
    public function it_carries_the_hub_protocol_version_into_a_manual_mercure_config(): void
    {
        $hubUrlResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('/.well-known/mercure');
        $hubUrlResolver->method('resolveProtocolVersion')->willReturn(MercureConfig::PROTOCOL_VERSION_1_0);

        $preparer = new RenderingPreparer(mercureHubUrlResolver: $hubUrlResolver);
        $table    = new DataTable('Test');
        $table->mercure(topics: ['/api/books/{id}']);

        $tablePayload = self::prepare($preparer, $table, null);

        $this->assertSame(MercureConfig::PROTOCOL_VERSION_1_0, $tablePayload->mercure?->protocolVersion);
    }

    #[Test]
    public function it_carries_the_hub_protocol_version_into_attribute_mercure_topics(): void
    {
        $hubUrlResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('/.well-known/mercure');
        $hubUrlResolver->method('resolveProtocolVersion')->willReturn(MercureConfig::PROTOCOL_VERSION_1_0);

        $preparer = new RenderingPreparer(mercureHubUrlResolver: $hubUrlResolver);
        $table    = (new DataTable('Test'))->ajax('/api/books');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: [
            'topics' => ['/api/books/{id}'],
        ]));

        $this->assertSame(MercureConfig::PROTOCOL_VERSION_1_0, $tablePayload->mercure?->protocolVersion);
    }

    #[Test]
    public function it_resolves_mercure_config_without_mutating_the_table(): void
    {
        $mercureConfig = (new MercureConfig(topics: ['/products/{id}']))
            ->withHubUrl('/.well-known/mercure');

        $mercureResolver = $this->createMock(MercureConfigResolver::class);
        $mercureResolver->method('resolveMercureConfig')
            ->with(\stdClass::class)
            ->willReturn($mercureConfig);

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = (new DataTable('Test'))->serverSide();

        $resolved = $preparer->resolveMercureConfig($table, $preparer->resolveOptIns($table, new AsDataTable(entityClass: \stdClass::class, mercure: true)));

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
        $mercureResolver = $this->createMock(MercureConfigResolver::class);
        $mercureResolver->expects($this->never())->method('resolveMercureConfig');

        $hubUrlResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('/.well-known/mercure');

        $preparer = new RenderingPreparer(
            mercureResolver: $mercureResolver,
            mercureHubUrlResolver: $hubUrlResolver,
        );
        $table = (new DataTable('Test'))->ajax('/api/books');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: [
            'topics'          => ['https://example.com/books'],
            'withCredentials' => true,
            'debounceMs'      => 250,
        ]));

        $this->assertSame([
            'hubUrl'          => '/.well-known/mercure',
            'topics'          => ['https://example.com/books'],
            'withCredentials' => true,
            'debounceMs'      => 250,
        ], $tablePayload->getView()['mercure']);
    }

    #[Test]
    public function it_subscribes_with_credentials_for_a_private_api_platform_resource(): void
    {
        $metadataResolver = $this->createStub(ApiResourceMercureMetadataResolver::class);
        $metadataResolver->method('resolveTopics')->willReturn(['https://example.com/api/books/{id}']);
        $metadataResolver->method('resolvePrivate')->willReturn(true);

        $hubUrlResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://example.com/.well-known/mercure');

        $preparer = new RenderingPreparer(
            mercureResolver: new MercureConfigResolver($hubUrlResolver, $metadataResolver),
            mercureHubUrlResolver: $hubUrlResolver,
        );
        $table = (new DataTable('Test'))->ajax('/api/books');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        $this->assertSame([
            'hubUrl'          => 'https://example.com/.well-known/mercure',
            'topics'          => ['https://example.com/api/books/{id}'],
            'withCredentials' => true,
        ], $tablePayload->getView()['mercure']);
    }

    #[Test]
    public function it_keeps_an_explicit_with_credentials_false_over_the_private_metadata(): void
    {
        $metadataResolver = $this->createMock(ApiResourceMercureMetadataResolver::class);
        $metadataResolver->expects($this->never())->method('resolveTopics');
        $metadataResolver->expects($this->never())->method('resolvePrivate');

        $hubUrlResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://example.com/.well-known/mercure');

        $preparer = new RenderingPreparer(
            mercureResolver: new MercureConfigResolver($hubUrlResolver, $metadataResolver),
            mercureHubUrlResolver: $hubUrlResolver,
        );
        $table = (new DataTable('Test'))->ajax('/api/books');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: [
            'topics'          => ['https://example.com/api/books/{id}'],
            'withCredentials' => false,
        ]));

        $this->assertSame([
            'hubUrl' => 'https://example.com/.well-known/mercure',
            'topics' => ['https://example.com/api/books/{id}'],
        ], $tablePayload->getView()['mercure']);
    }

    /**
     * @param \Closure(DataTable): DataTable $configure
     */
    #[Test]
    #[DataProvider('provideCasesSkippingMercure')]
    public function it_skips_mercure_without_consulting_the_resolver(AsDataTable $attribute, \Closure $configure): void
    {
        $mercureResolver = $this->createMock(MercureConfigResolver::class);
        $mercureResolver->expects($this->never())->method('resolveMercureConfig');

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = $configure(new DataTable('Test'));

        $tablePayload = self::prepare($preparer, $table, $attribute);

        $this->assertNull($tablePayload->mercure);
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
        $mercureResolver = $this->createMock(MercureConfigResolver::class);
        $mercureResolver->expects($this->never())->method('resolveMercureConfig');

        $hubUrlResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('/.well-known/mercure');

        $preparer = new RenderingPreparer(
            mercureResolver: $mercureResolver,
            mercureHubUrlResolver: $hubUrlResolver,
        );
        $table = new DataTable('Test');
        $table->mercure(topics: ['/existing']);

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        $this->assertSame('/.well-known/mercure', $tablePayload->mercure->hubUrl);
        $this->assertSame(['/existing'], $tablePayload->mercure->topics);
    }

    #[Test]
    public function it_throws_when_manual_mercure_has_no_resolvable_hub_url(): void
    {
        $hubUrlResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn(null);

        $preparer = new RenderingPreparer(mercureHubUrlResolver: $hubUrlResolver);
        $table    = new DataTable('Test');
        $table->mercure(topics: ['/existing']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Mercure hub URL could not be resolved');

        $tablePayload = self::prepare($preparer, $table, null);
    }

    #[Test]
    public function it_skips_mercure_when_resolver_returns_null(): void
    {
        $mercureResolver = $this->createStub(MercureConfigResolver::class);
        $mercureResolver->method('resolveMercureConfig')->willReturn(null);

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = new DataTable('Test');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        $this->assertNull($tablePayload->mercure);
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

        $tablePayload = self::prepare($preparer, $table, null);

        $ajax = self::optionOf($tablePayload, 'ajax');
        $this->assertIsArray($ajax);
        $this->assertSame('/datatables/ajax/data', $ajax['url']);
        $this->assertSame('GET', $ajax['type']);
        $this->assertSame(['table' => $registry->getToken(self::TABLE_CLASS)], $ajax['data']);
        $this->assertStringNotContainsString('UserDataTable', $ajax['data']['table']);
    }

    /**
     * @param \Closure(): Button $button
     */
    #[Test]
    #[DataProvider('provideServerSideExportButtons')]
    public function it_injects_an_export_url_when_a_server_side_export_button_is_present(\Closure $button): void
    {
        $registry     = $this->createAjaxRegistry(self::TABLE_SERVICE_IDS);
        $token        = $registry->getToken(self::TABLE_CLASS);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with(RenderingPreparer::AJAX_EXPORT_ROUTE, ['table' => $token])
            ->willReturn('/datatables/ajax/export?table='.$token);

        $preparer = new RenderingPreparer(urlGenerator: $urlGenerator, ajaxRegistry: $registry);
        $table    = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->addExtension(new ButtonsExtension([$button()]));

        $tablePayload = self::prepare($preparer, $table, null);

        $this->assertSame('/datatables/ajax/export?table='.$token, self::optionOf($tablePayload, 'exportUrl'));
    }

    /**
     * @return iterable<string, array{\Closure(): Button}>
     */
    public static function provideServerSideExportButtons(): iterable
    {
        yield 'csv' => [static fn (): Button => Button::csv(serverSide: true)->filename('users')];
        yield 'xlsx' => [static fn (): Button => Button::excel(serverSide: true)->filename('users')];
        yield 'nested in a collection' => [
            static fn (): Button => Button::collection([Button::excel(serverSide: true)]),
        ];
    }

    #[Test]
    public function it_injects_the_bulk_endpoint_and_the_translated_bar_labels(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->with(RenderingPreparer::AJAX_BULK_ROUTE)
            ->willReturn('/datatables/ajax/bulk');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'tr:'.$id);

        $preparer = new RenderingPreparer(translator: $translator, urlGenerator: $urlGenerator);
        $table    = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->setBulkActions((new BulkActions())->add(BulkAction::new('approve')));

        $tablePayload = self::prepare($preparer, $table, null);

        $bulk = $tablePayload->getView()['bulkActions'];

        $this->assertSame('/datatables/ajax/bulk', $bulk['url']);
        $this->assertSame('tr:bulk.bar.trigger', $bulk['labels']['trigger']);
        $this->assertSame('tr:bulk.bar.selected', $bulk['labels']['selected']);
        $this->assertSame('tr:bulk.bar.skipped', $bulk['labels']['skipped']);
        $this->assertSame('tr:bulk.bar.failed', $bulk['labels']['failed']);
    }

    #[Test]
    public function it_translates_the_row_action_labels_only_for_a_table_with_row_actions(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'tr:'.$id);

        $preparer = new RenderingPreparer(translator: $translator);

        $withActions = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->add(ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete())));
        $withoutActions = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->add(TextColumn::new('name'));

        $withActionsPayload    = self::prepare($preparer, $withActions, null);
        $withoutActionsPayload = self::prepare($preparer, $withoutActions, null);

        $this->assertSame([
            'failed'    => 'tr:action.row.failed',
            'forbidden' => 'tr:action.row.forbidden',
            'confirm'   => 'tr:action.row.confirm',
            'cancel'    => 'tr:action.row.cancel',
        ], $withActionsPayload->getView()['actionLabels']);
        $this->assertArrayNotHasKey('actionLabels', $withoutActionsPayload->getView());
    }

    #[Test]
    public function it_generates_the_row_action_urls_only_for_a_table_with_row_actions(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route): string => '/p/'.$route);

        $preparer = new RenderingPreparer(urlGenerator: $urlGenerator);

        $withActions = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->add(ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete())));
        $withoutActions = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->add(TextColumn::new('name'));

        $withActionsPayload    = self::prepare($preparer, $withActions, null);
        $withoutActionsPayload = self::prepare($preparer, $withoutActions, null);

        $this->assertSame([
            'delete'         => '/p/ux_datatables_ajax_delete',
            'toggle'         => '/p/ux_datatables_ajax_edit',
            'detail'         => '/p/ux_datatables_ajax_detail',
            'editForm'       => '/p/ux_datatables_ajax_edit_form',
            'editFormSubmit' => '/p/ux_datatables_ajax_edit_form_submit',
        ], $withActionsPayload->getView()['rowActionUrls']);
        $this->assertArrayNotHasKey('rowActionUrls', $withoutActionsPayload->getView());
    }

    #[Test]
    public function it_translates_the_summary_labels_only_for_a_table_with_summaries(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'tr:'.$id);

        $preparer = new RenderingPreparer(translator: $translator);

        $summarized = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->add(NumberColumn::new('price')->summarize(new Sum()));
        $plain = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->add(TextColumn::new('name'));

        $summarizedPayload = self::prepare($preparer, $summarized, null);
        $plainPayload      = self::prepare($preparer, $plain, null);

        $this->assertSame([
            'sum'   => 'tr:summary.sum',
            'avg'   => 'tr:summary.avg',
            'min'   => 'tr:summary.min',
            'max'   => 'tr:summary.max',
            'count' => 'tr:summary.count',
        ], $summarizedPayload->getView()['summaryLabels']);
        $this->assertArrayNotHasKey('summaryLabels', $plainPayload->getView());
    }

    #[Test]
    public function it_translates_the_row_action_labels_of_a_custom_column_providing_actions(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'tr:'.$id);

        $column = new class('custom') extends AbstractColumn implements ActionsProvidingColumnInterface {
            public function __construct(string $name)
            {
                $this->type  = ColumnType::HTML;
                $this->name  = $name;
                $this->title = 'Custom';
            }

            public function getActions(): ?Actions
            {
                return (new Actions())->add(Action::delete());
            }
        };

        $table = (new DataTable('Test'))->setDataTableClass(self::TABLE_CLASS)->add($column);

        $tablePayload = self::prepare(new RenderingPreparer(translator: $translator), $table, null);

        $this->assertSame('tr:action.row.failed', $tablePayload->getView()['actionLabels']['failed']);
    }

    #[Test]
    public function it_translates_the_labels_of_choice_columns(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'tr:'.$id);

        $table = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->add(ChoiceColumn::new('role', 'Role')->choices(RenderingPreparerRole::class));

        $tablePayload = self::prepare(new RenderingPreparer(translator: $translator), $table, null);

        $this->assertSame(['admin' => 'Admin'], $table->getColumns()['role']->getCustomOptions()['choices']);
        $this->assertSame(
            ['admin' => 'tr:role.admin'],
            $tablePayload->configuredColumns[0]->getCustomOptions()['choices'],
        );
    }

    #[Test]
    public function it_generates_no_bulk_endpoint_without_bulk_actions(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $preparer = new RenderingPreparer(urlGenerator: $urlGenerator);
        $table    = (new DataTable('Test'))->setDataTableClass(self::TABLE_CLASS);

        $tablePayload = self::prepare($preparer, $table, null);

        $this->assertArrayNotHasKey('bulkActions', $tablePayload->getView());
    }

    #[Test]
    public function it_renders_no_export_url_for_an_api_platform_table_without_a_collection_and_throws(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $resolver = $this->createStub(ApiResourceCollectionUrlResolver::class);
        $resolver->method('resolveCollectionUrl')->willReturn(null);

        $preparer = new RenderingPreparer(
            urlResolver: $resolver,
            urlGenerator: $urlGenerator,
            ajaxRegistry: $this->createAjaxRegistry(self::TABLE_SERVICE_IDS),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->addExtension(new ButtonsExtension([Button::csv(serverSide: true)]));

        try {
            $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));
            $this->fail('An API Platform opt-in without a collection must throw.');
        } catch (\LogicException) {
        }

        $this->assertNull($table->getBundleOption('exportUrl'));
    }

    #[Test]
    public function it_leaves_the_export_url_alone_for_client_side_export_buttons(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $preparer = new RenderingPreparer(
            urlGenerator: $urlGenerator,
            ajaxRegistry: $this->createAjaxRegistry(self::TABLE_SERVICE_IDS),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->addExtension(new ButtonsExtension([Button::csv(), Button::excel()]));

        $tablePayload = self::prepare($preparer, $table, null);

        $this->assertNull(self::optionOf($tablePayload, 'exportUrl'));
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

        $tablePayload = self::prepare($preparer, $table, null);

        $this->assertSame($expectedAjax, self::optionOf($tablePayload, 'ajax'));
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
        $urlResolver = $this->createStub(ApiResourceCollectionUrlResolver::class);
        $urlResolver->method('resolveCollectionUrl')->willReturn('/api/users');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver, urlGenerator: $urlGenerator);
        $table    = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->serverSide();

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));

        $this->assertSame('/api/users', self::optionOf($tablePayload, 'ajax')['url']);
    }

    #[Test]
    public function it_wires_no_endpoint_for_a_server_side_table_whose_api_platform_collection_is_unresolvable(): void
    {
        $urlResolver = $this->createStub(ApiResourceCollectionUrlResolver::class);
        $urlResolver->method('resolveCollectionUrl')->willReturn(null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/datatables/ajax/data');

        $preparer = new RenderingPreparer(
            urlResolver: $urlResolver,
            urlGenerator: $urlGenerator,
            ajaxRegistry: $this->createAjaxRegistry(self::TABLE_SERVICE_IDS),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass(self::TABLE_CLASS)
            ->serverSide()
            ->apiPlatform();

        try {
            $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class));
            $this->fail('An API Platform opt-in without a collection must throw.');
        } catch (\LogicException) {
        }

        $this->assertNull($table->getOption('ajax'));
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
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
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

        $tablePayload = self::prepare($preparer, $table, null);

        $this->assertSame([
            'type' => 'GET',
            'url'  => '/datatables/ajax/data',
            'data' => array_merge(['table' => $registry->getToken(self::TABLE_CLASS)], $expectedForwardedData),
        ], self::optionOf($tablePayload, 'ajax'));
        $this->assertSame([] === $expectedForwardedData ? null : $expectedForwardedData, $tablePayload->getView()['forwardedQuery'] ?? null);
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

        $tablePayload = self::prepare($preparer, $table, null);

        $ajax = self::optionOf($tablePayload, 'ajax');
        $this->assertSame('/custom-endpoint', $ajax['url']);
        $this->assertSame(['q' => 'foo'], $ajax['data']);
    }

    #[Test]
    public function it_translates_into_the_payload_and_leaves_the_configured_table_untouched(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 'T('.$id.')');

        $searchList = SearchList::new()->options(RenderingPreparerSearchListStatus::class);
        $table      = (new DataTable('Test'))
            ->columns([
                TextColumn::new('name', 'col.name'),
                ChoiceColumn::new('role', 'col.role')->choices(RenderingPreparerRole::class),
            ])
            ->addExtension((new ColumnControlExtension([]))->add(1, [$searchList]))
            ->setFilters((new Filters())
                ->add(TextFilter::new('name')->label('filter.name')->placeholder('filter.name.hint'))
                ->add(NumberRangeFilter::new('price'))
                ->add(ChoiceFilter::new('role')->label('filter.role')->options(RenderingPreparerRole::class)));
        $configured = [
            'columns'    => array_map(static fn (ColumnInterface $column): array => $column->jsonSerialize(), $table->getColumns()),
            'filters'    => $table->getFilters()->jsonSerialize(),
            'searchList' => $searchList->jsonSerialize(),
        ];
        $preparer = new RenderingPreparer(translator: $translator);

        $first  = self::prepare($preparer, $table, null)->getView();
        $second = self::prepare($preparer, $table, null)->getView();

        $this->assertSame(json_encode($first), json_encode($second));
        $this->assertSame('T(col.name)', $first['columns'][0]['title']);
        $this->assertSame('T(filter.name)', $first['filters'][0]['label']);
        $this->assertSame('T(status.draft)', $first['columnControl'][0]['content'][0]['options'][0]['label']);
        $this->assertSame('col.name', $table->getColumns()['name']->getTitle());
        $this->assertSame($configured, [
            'columns'    => array_map(static fn (ColumnInterface $column): array => $column->jsonSerialize(), $table->getColumns()),
            'filters'    => $table->getFilters()->jsonSerialize(),
            'searchList' => $searchList->jsonSerialize(),
        ]);
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

        $tablePayload = self::prepare($preparer, $table, null);

        $this->assertSame('Status', $table->getColumns()['status']->getTitle());
        $this->assertSame('Statut', $tablePayload->getView()['columns'][0]['title']);
    }

    #[Test]
    public function it_translates_filter_label_and_placeholder_keys(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnMap([
                ['user.last_login', [], null, null, 'Dernière connexion'],
                ['user.last_login.placeholder', [], null, null, 'Choisir une date'],
                ['filter.bar.title', [], FilterLabels::DOMAIN, null, 'Filtres'],
                ['filter.bar.reset', [], FilterLabels::DOMAIN, null, 'Réinitialiser'],
                ['filter.bar.apply', [], FilterLabels::DOMAIN, null, 'Appliquer les filtres'],
                ['filter.bar.all', [], FilterLabels::DOMAIN, null, 'Tous'],
            ]);

        $filters = (new Filters())->add(
            TextFilter::new('lastLoginAt')
                ->label('user.last_login')
                ->placeholder('user.last_login.placeholder'),
        );
        $table = (new DataTable('Test'))->setFilters($filters);

        $tablePayload = self::prepare(new RenderingPreparer(translator: $translator), $table, null);

        $payload = $tablePayload->getView()['filters'][0];
        $this->assertSame('Dernière connexion', $payload['label']);
        $this->assertSame('Choisir une date', $payload['placeholder']);
    }

    #[Test]
    public function it_leaves_humanized_filter_names_untranslated(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static function (string $id, array $parameters = [], ?string $domain = null): string {
                if (FilterLabels::DOMAIN === $domain && str_starts_with($id, 'filter.bar.')) {
                    return $id;
                }

                self::fail(\sprintf('Unexpected translation id "%s".', $id));
            });

        $filters = (new Filters())->add(TextFilter::new('lastLoginAt'));
        $table   = (new DataTable('Test'))->setFilters($filters);

        $tablePayload = self::prepare(new RenderingPreparer(translator: $translator), $table, null);

        $this->assertSame('Last Login At', $tablePayload->getView()['filters'][0]['label']);
    }

    /**
     * Every other translation test configures a single filter, so none of them would notice a
     * loop that stops after the first one. This one asserts the payload of both.
     */
    #[Test]
    public function it_translates_the_labels_of_every_configured_filter(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnMap([
                ['user.status', [], null, null, 'Statut'],
                ['user.last_login', [], null, null, 'Derniere connexion'],
                ['filter.bar.title', [], FilterLabels::DOMAIN, null, 'Filtres'],
                ['filter.bar.reset', [], FilterLabels::DOMAIN, null, 'Reinitialiser'],
                ['filter.bar.apply', [], FilterLabels::DOMAIN, null, 'Appliquer les filtres'],
                ['filter.bar.all', [], FilterLabels::DOMAIN, null, 'Tous'],
            ]);

        $filters = (new Filters())
            ->add(TextFilter::new('status')->label('user.status'))
            ->add(TextFilter::new('lastLoginAt')->label('user.last_login'));

        $table = (new DataTable('Test'))->setFilters($filters);

        $tablePayload = self::prepare(new RenderingPreparer(translator: $translator), $table, null);

        $this->assertSame(
            ['Statut', 'Derniere connexion'],
            array_column($tablePayload->getView()['filters'], 'label'),
        );
    }

    #[Test]
    public function it_translates_translatable_filter_option_labels(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
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

        $preparer     = new RenderingPreparer(translator: $translator);
        $tablePayload = self::prepare($preparer, $table, null);

        $this->assertSame(
            ['admin' => 'Administrateur'],
            $tablePayload->getView()['filters'][0]['options'],
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
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap($translationMap);

        $filters = new Filters();
        if ([] !== $labelOverrides) {
            $filters->labels(...$labelOverrides);
        }
        $filters->add(TextFilter::new('name'));

        $table = (new DataTable('Test'))->setFilters($filters);

        $tablePayload = self::prepare(new RenderingPreparer(translator: $translator), $table, null);

        $this->assertSame($expected, $tablePayload->getView()['filterLabels']);
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

    #[Test]
    public function it_resolves_auto_topics_when_the_attribute_mercure_array_has_none(): void
    {
        $mercureConfig = (new MercureConfig(topics: ['https://example.com/api/books/{id}']))
            ->withHubUrl('https://example.com/.well-known/mercure');

        $mercureResolver = $this->createMock(MercureConfigResolver::class);
        $mercureResolver->expects($this->once())
            ->method('resolveMercureConfig')
            ->with(\stdClass::class)
            ->willReturn($mercureConfig);

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = (new DataTable('Test'))->ajax('/api/books');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: [
            'debounceMs' => 250,
        ]));

        $this->assertSame([
            'hubUrl'     => 'https://example.com/.well-known/mercure',
            'topics'     => ['https://example.com/api/books/{id}'],
            'debounceMs' => 250,
        ], $tablePayload->getView()['mercure']);
    }

    #[Test]
    public function it_applies_an_explicit_with_credentials_over_the_auto_resolved_one(): void
    {
        $mercureConfig = (new MercureConfig(topics: ['/api/books/{id}'], withCredentials: true))
            ->withHubUrl('/.well-known/mercure');

        $mercureResolver = $this->createStub(MercureConfigResolver::class);
        $mercureResolver->method('resolveMercureConfig')->willReturn($mercureConfig);

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = (new DataTable('Test'))->ajax('/api/books');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: [
            'withCredentials' => false,
        ]));

        $this->assertSame([
            'hubUrl' => '/.well-known/mercure',
            'topics' => ['/api/books/{id}'],
        ], $tablePayload->getView()['mercure']);
    }

    /**
     * @param array<string, mixed> $mercure
     */
    #[Test]
    #[DataProvider('provideTopiclessMercureOptions')]
    public function it_skips_mercure_without_topics_when_the_auto_resolver_finds_none(array $mercure): void
    {
        $mercureResolver = $this->createStub(MercureConfigResolver::class);
        $mercureResolver->method('resolveMercureConfig')->willReturn(null);

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = (new DataTable('Test'))->ajax('/api/books');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: $mercure));

        $this->assertArrayNotHasKey('mercure', $tablePayload->getView());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideTopiclessMercureOptions(): iterable
    {
        yield 'empty array' => [[]];

        yield 'subscription options only' => [['debounceMs' => 250, 'withCredentials' => true]];
    }

    #[Test]
    public function it_names_the_attribute_and_the_class_for_an_unknown_mercure_option(): void
    {
        $preparer = new RenderingPreparer();
        $table    = (new DataTable('Test'))->ajax('/api/books');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown mercure option(s) "topic" declared on #[AsDataTable] for "stdClass". Supported options are "topics", "withCredentials" and "debounceMs".');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: [
            'topic' => 'https://example.com/books',
        ]));
    }

    /**
     * @param array<string, mixed> $mercure
     */
    #[Test]
    #[DataProvider('provideInvalidMercureOptions')]
    public function it_rejects_an_invalid_mercure_option(string $expectedMessage, array $mercure): void
    {
        $preparer = new RenderingPreparer();
        $table    = (new DataTable('Test'))->ajax('/api/books');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(entityClass: \stdClass::class, mercure: $mercure));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function provideInvalidMercureOptions(): iterable
    {
        yield 'topics is neither a string nor an array' => [
            'The mercure "topics" option declared on #[AsDataTable] for "stdClass" must be a string or an array of strings.',
            ['topics' => 42],
        ];

        yield 'withCredentials is not a boolean' => [
            'The mercure "withCredentials" option declared on #[AsDataTable] for "stdClass" must be a boolean.',
            ['topics' => ['/api/books/{id}'], 'withCredentials' => 'yes'],
        ];

        yield 'debounceMs is not an integer' => [
            'The mercure "debounceMs" option declared on #[AsDataTable] for "stdClass" must be an integer or null.',
            ['topics' => ['/api/books/{id}'], 'debounceMs' => '250'],
        ];
    }

    #[Test]
    public function it_ignores_an_empty_edit_modal_override(): void
    {
        $preparer = new RenderingPreparer();
        $table    = new DataTable('Test');

        $tablePayload = self::prepare($preparer, $table, new AsDataTable(
            entityClass: \stdClass::class,
            editModalTemplate: '',
            editModalAdapter: '   ',
        ));

        $this->assertNull($table->getEditModalTemplate());
        $this->assertNull($table->getEditModalAdapter());
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

    private static function prepare(RenderingPreparer $preparer, DataTable $table, ?AsDataTable $asDataTable): PreparedDataTable
    {
        return self::prepareWith($preparer, $table, $preparer->resolveOptIns($table, $asDataTable));
    }
}
