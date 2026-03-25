<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Rendering;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

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

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class));

        $ajax = $table->getOption('ajax');
        $this->assertIsArray($ajax);
        $this->assertSame('/api/products', $ajax['url']);
        $this->assertTrue($table->getOption('apiPlatform'));
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

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class));

        $this->assertNull($table->getOption('ajax'));
    }

    #[Test]
    public function it_configures_mercure(): void
    {
        $mercureConfig = new MercureConfig(
            hubUrl: '/.well-known/mercure',
            topics: ['/products/{id}'],
        );

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
    public function it_skips_mercure_when_already_configured(): void
    {
        $mercureResolver = $this->createMock(MercureConfigResolverInterface::class);
        $mercureResolver->expects($this->never())->method('resolveMercureConfig');

        $preparer = new RenderingPreparer(mercureResolver: $mercureResolver);
        $table    = new DataTable('Test');
        $table->mercure(hubUrl: '/hub', topics: ['/existing']);

        $preparer->prepare($table, new AsDataTable(entityClass: \stdClass::class, mercure: true));

        $this->assertSame('/hub', $table->getMercureConfig()->hubUrl);
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
}
