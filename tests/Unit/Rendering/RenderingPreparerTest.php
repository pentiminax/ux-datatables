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
    #[Test]
    public function it_does_nothing_without_resolvers(): void
    {
        $preparer = new RenderingPreparer();
        $table    = new DataTable('Test');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class));

        $this->assertNull($table->getOption('ajax'));
        $this->assertNull($table->getMercureConfig());
    }

    #[Test]
    public function it_does_nothing_without_attribute(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->expects($this->never())->method('resolveCollectionUrl');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = new DataTable('Test');

        $preparer->prepare($table, null);

        $this->assertNull($table->getOption('ajax'));
    }

    #[Test]
    public function it_configures_api_platform_ajax(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/products');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = new DataTable('Test');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));

        $ajax = $table->getOption('ajax');
        $this->assertIsArray($ajax);
        $this->assertSame('/api/products', $ajax['url']);
        $this->assertTrue($table->getOption('apiPlatform'));
    }

    #[Test]
    public function it_skips_api_platform_without_opt_in(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->expects($this->never())->method('resolveCollectionUrl');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = new DataTable('Test');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class));

        $this->assertNull($table->getOption('ajax'));
        $this->assertNull($table->getOption('apiPlatform'));
    }

    #[Test]
    public function it_configures_api_platform_ajax_when_opted_in_via_configure_data_table(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/products');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = new DataTable('Test');
        $table->apiPlatform(true);

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class));

        $ajax = $table->getOption('ajax');
        $this->assertIsArray($ajax);
        $this->assertSame('/api/products', $ajax['url']);
    }

    #[Test]
    public function it_configures_api_platform_template_rendering_for_template_columns(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/users');

        $registry = $this->createAjaxRegistry(['App\\DataTables\\UserDataTable' => 'app.users_datatable']);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('ux_datatables_ajax_templates')
            ->willReturn('/datatables/ajax/templates');

        $preparer = new RenderingPreparer(
            urlResolver: $urlResolver,
            urlGenerator: $urlGenerator,
            ajaxRegistry: $registry,
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->columns([
                TemplateColumn::new('avatar', 'Avatar')
                    ->setTemplate('user.html.twig'),
                TextColumn::new('email', 'Email'),
            ]);

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));

        $this->assertSame([
            'url'   => '/datatables/ajax/templates',
            'table' => $registry->getToken('App\\DataTables\\UserDataTable'),
        ], $table->getOption('apiPlatformTemplateRendering'));
    }

    #[Test]
    public function it_skips_api_platform_template_rendering_without_template_columns(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->method('resolveCollectionUrl')->willReturn('/api/users');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $preparer = new RenderingPreparer(
            urlResolver: $urlResolver,
            urlGenerator: $urlGenerator,
            ajaxRegistry: $this->createAjaxRegistry(['App\\DataTables\\UserDataTable' => 'app.users_datatable']),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->columns([TextColumn::new('email', 'Email')]);

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));

        $this->assertNull($table->getOption('apiPlatformTemplateRendering'));
    }

    #[Test]
    public function it_skips_ajax_when_already_set(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->expects($this->never())->method('resolveCollectionUrl');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = new DataTable('Test');
        $table->ajax('/custom-url');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class));

        $this->assertSame('/custom-url', $table->getOption('ajax')['url']);
    }

    #[Test]
    public function it_skips_ajax_when_data_is_set(): void
    {
        $urlResolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $urlResolver->expects($this->never())->method('resolveCollectionUrl');

        $preparer = new RenderingPreparer(urlResolver: $urlResolver);
        $table    = new DataTable('Test');
        $table->data([['id' => 1]]);

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class));

        $this->assertNull($table->getOption('apiPlatform'));
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

    #[Test]
    public function it_skips_mercure_when_attribute_mercure_is_false(): void
    {
        $mercureResolver = $this->createMock(MercureConfigResolverInterface::class);
        $mercureResolver->expects($this->never())->method('resolveMercureConfig');

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = new DataTable('Test');

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, mercure: false));

        $this->assertNull($table->getMercureConfig());
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
    public function it_skips_mercure_when_data_without_ajax(): void
    {
        $mercureResolver = $this->createMock(MercureConfigResolverInterface::class);
        $mercureResolver->expects($this->never())->method('resolveMercureConfig');

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = new DataTable('Test');
        $table->data([['id' => 1]]);

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        $this->assertNull($table->getMercureConfig());
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

        $registry = $this->createAjaxRegistry(['App\\DataTables\\UserDataTable' => 'app.users_datatable']);
        $preparer = new RenderingPreparer(urlGenerator: $urlGenerator, ajaxRegistry: $registry);
        $table    = (new DataTable('Test'))
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->serverSide();

        $preparer->prepare($table, null);

        $ajax = $table->getOption('ajax');
        $this->assertIsArray($ajax);
        $this->assertSame('/datatables/ajax/data', $ajax['url']);
        $this->assertSame('GET', $ajax['type']);
        $this->assertSame(['table' => $registry->getToken('App\\DataTables\\UserDataTable')], $ajax['data']);
        $this->assertStringNotContainsString('UserDataTable', $ajax['data']['table']);
    }

    #[Test]
    public function it_does_not_auto_configure_ajax_for_client_side_table(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $preparer = new RenderingPreparer(urlGenerator: $urlGenerator);
        $table    = (new DataTable('Test'))->setDataTableClass('App\\DataTables\\UserDataTable');

        $preparer->prepare($table, null);

        $this->assertNull($table->getOption('ajax'));
    }

    #[Test]
    public function it_does_not_override_manual_ajax_url(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $preparer = new RenderingPreparer(
            urlGenerator: $urlGenerator,
            ajaxRegistry: $this->createAjaxRegistry(['App\\DataTables\\UserDataTable' => 'app.users_datatable']),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->serverSide()
            ->ajax('/custom-endpoint');

        $preparer->prepare($table, null);

        $this->assertSame('/custom-endpoint', $table->getOption('ajax')['url']);
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
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->serverSide();

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, apiPlatform: true));

        $this->assertSame('/api/users', $table->getOption('ajax')['url']);
    }

    #[Test]
    public function it_does_not_auto_configure_ajax_when_url_generator_is_missing(): void
    {
        $preparer = new RenderingPreparer(
            ajaxRegistry: $this->createAjaxRegistry(['App\\DataTables\\UserDataTable' => 'app.users_datatable']),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->serverSide();

        $preparer->prepare($table, null);

        $this->assertNull($table->getOption('ajax'));
    }

    #[Test]
    public function it_does_not_auto_configure_ajax_when_registry_is_missing(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $preparer = new RenderingPreparer(urlGenerator: $urlGenerator);
        $table    = (new DataTable('Test'))
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->serverSide();

        $preparer->prepare($table, null);

        $this->assertNull($table->getOption('ajax'));
    }

    #[Test]
    public function it_does_not_auto_configure_ajax_when_fqcn_is_missing(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $preparer = new RenderingPreparer(
            urlGenerator: $urlGenerator,
            ajaxRegistry: $this->createAjaxRegistry(['App\\DataTables\\UserDataTable' => 'app.users_datatable']),
        );
        $table = (new DataTable('Test'))->serverSide();

        $preparer->prepare($table, null);

        $this->assertNull($table->getOption('ajax'));
    }

    #[Test]
    public function it_forwards_present_query_parameters_into_auto_ajax_data(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/datatables/ajax/data');

        $registry = $this->createAjaxRegistry(['App\\DataTables\\UserDataTable' => 'app.users_datatable']);
        $preparer = new RenderingPreparer(
            urlGenerator: $urlGenerator,
            ajaxRegistry: $registry,
            requestStack: $this->createRequestStack(['q' => 'foo', 'pending' => '1', 'unrelated' => 'x']),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->serverSide()
            ->forwardQueryParameters(['q', 'pending']);

        $preparer->prepare($table, null);

        $data = $table->getOption('ajax')['data'];
        $this->assertSame($registry->getToken('App\\DataTables\\UserDataTable'), $data['table']);
        $this->assertSame('foo', $data['q']);
        $this->assertSame('1', $data['pending']);
        $this->assertArrayNotHasKey('unrelated', $data);
    }

    #[Test]
    public function it_forwards_only_query_parameters_present_in_the_request(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/datatables/ajax/data');

        $preparer = new RenderingPreparer(
            urlGenerator: $urlGenerator,
            ajaxRegistry: $this->createAjaxRegistry(['App\\DataTables\\UserDataTable' => 'app.users_datatable']),
            requestStack: $this->createRequestStack(['q' => 'foo']),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->serverSide()
            ->forwardQueryParameters(['q', 'pending']);

        $preparer->prepare($table, null);

        $data = $table->getOption('ajax')['data'];
        $this->assertSame('foo', $data['q']);
        $this->assertArrayNotHasKey('pending', $data);
    }

    #[Test]
    public function it_does_not_forward_query_parameters_without_a_current_request(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/datatables/ajax/data');

        $registry = $this->createAjaxRegistry(['App\\DataTables\\UserDataTable' => 'app.users_datatable']);
        $preparer = new RenderingPreparer(
            urlGenerator: $urlGenerator,
            ajaxRegistry: $registry,
            requestStack: new RequestStack(),
        );
        $table = (new DataTable('Test'))
            ->setDataTableClass('App\\DataTables\\UserDataTable')
            ->serverSide()
            ->forwardQueryParameters(['q']);

        $preparer->prepare($table, null);

        $this->assertSame(
            ['table' => $registry->getToken('App\\DataTables\\UserDataTable')],
            $table->getOption('ajax')['data'],
        );
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

    private function createRequestStack(array $query): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(new Request($query));

        return $stack;
    }

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

    #[Test]
    public function it_translates_overridden_filter_bar_labels_and_fills_remaining_with_defaults(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnMap([
                // Developer overrides are translated in the default domain.
                ['filter.title', [], null, null, 'Filtres'],
                ['filter.apply', [], null, null, 'Appliquer'],
                // Untouched labels fall back to the bundle defaults (DataTables domain).
                ['filter.bar.reset', [], FilterLabels::DOMAIN, null, 'Réinitialiser'],
                ['filter.bar.all', [], FilterLabels::DOMAIN, null, 'Tous'],
            ]);

        $filters = (new Filters())
            ->labels(title: 'filter.title', apply: 'filter.apply')
            ->add(TextFilter::new('name'));
        $table = (new DataTable('Test'))->setFilters($filters);

        $preparer = new RenderingPreparer(translator: $translator);
        $preparer->prepare($table, null);

        $this->assertSame(
            [
                'title' => 'Filtres',
                'reset' => 'Réinitialiser',
                'apply' => 'Appliquer',
                'all'   => 'Tous',
            ],
            $table->getOptions()['filterLabels'],
        );
    }

    #[Test]
    public function it_provides_localized_default_filter_bar_labels_without_overrides(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnMap([
                ['filter.bar.title', [], FilterLabels::DOMAIN, null, 'Filtres'],
                ['filter.bar.reset', [], FilterLabels::DOMAIN, null, 'Réinitialiser'],
                ['filter.bar.apply', [], FilterLabels::DOMAIN, null, 'Appliquer les filtres'],
                ['filter.bar.all', [], FilterLabels::DOMAIN, null, 'Tous'],
            ]);

        $filters = (new Filters())->add(TextFilter::new('name'));
        $table   = (new DataTable('Test'))->setFilters($filters);

        $preparer = new RenderingPreparer(translator: $translator);
        $preparer->prepare($table, null);

        $this->assertSame(
            [
                'title' => 'Filtres',
                'reset' => 'Réinitialiser',
                'apply' => 'Appliquer les filtres',
                'all'   => 'Tous',
            ],
            $table->getOptions()['filterLabels'],
        );
    }
}
