<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mercure;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use Pentiminax\UX\DataTables\Mercure\MercureTableConfigResolver;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\ResolvedOptIns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MercureTableConfigResolver::class)]
final class MercureTableConfigResolverTest extends TestCase
{
    private const string HUB_URL = 'https://example.com/.well-known/mercure';

    #[Test]
    public function it_resolves_the_entity_topics_when_the_attribute_enables_mercure(): void
    {
        $autoConfig = new MercureConfig(topics: ['/products/{id}'], hubUrl: self::HUB_URL);

        $configResolver = $this->createMock(MercureConfigResolver::class);
        $configResolver->expects($this->once())->method('resolveMercureConfig')->with(\stdClass::class)->willReturn($autoConfig);

        $resolver = new MercureTableConfigResolver($configResolver, $this->hub(self::HUB_URL));

        $this->assertSame($autoConfig, $resolver->resolve($this->optIns(new DataTable('t'), new AsDataTable(entityClass: \stdClass::class, mercure: true))));
    }

    #[Test]
    public function it_prefers_the_manual_config_and_resolves_its_hub_url_and_protocol(): void
    {
        $configResolver = $this->createMock(MercureConfigResolver::class);
        $configResolver->expects($this->never())->method('resolveMercureConfig');

        $table = new DataTable('t');
        $table->mercure(topics: ['/manual']);

        $config = (new MercureTableConfigResolver($configResolver, $this->hub(self::HUB_URL, MercureConfig::PROTOCOL_VERSION_1_0)))
            ->resolve($this->optIns($table, new AsDataTable(entityClass: \stdClass::class, mercure: ['topics' => ['/attribute']])));

        $this->assertSame(['/manual'], $config?->topics);
        $this->assertSame(self::HUB_URL, $config->hubUrl);
        $this->assertSame(MercureConfig::PROTOCOL_VERSION_1_0, $config->protocolVersion);
    }

    #[Test]
    public function it_prefers_the_attribute_topics_over_the_auto_resolver(): void
    {
        $configResolver = $this->createMock(MercureConfigResolver::class);
        $configResolver->expects($this->never())->method('resolveMercureConfig');

        $config = (new MercureTableConfigResolver($configResolver, $this->hub(self::HUB_URL, MercureConfig::PROTOCOL_VERSION_1_0)))
            ->resolve($this->optIns((new DataTable('t'))->ajax('/api/books'), new AsDataTable(entityClass: \stdClass::class, mercure: [
                'topics'          => 'https://example.com/books',
                'withCredentials' => true,
                'debounceMs'      => 250,
            ])));

        $this->assertSame(['https://example.com/books'], $config?->topics);
        $this->assertTrue($config->withCredentials);
        $this->assertSame(250, $config->debounceMs);
        $this->assertSame(self::HUB_URL, $config->hubUrl);
        $this->assertSame(MercureConfig::PROTOCOL_VERSION_1_0, $config->protocolVersion);
    }

    #[Test]
    public function it_keeps_an_explicit_with_credentials_false_over_the_private_metadata(): void
    {
        $metadata = $this->createMock(ApiResourceMercureMetadataResolver::class);
        $metadata->expects($this->never())->method('resolveTopics');
        $metadata->expects($this->never())->method('resolvePrivate');

        $hub    = $this->hub(self::HUB_URL);
        $config = (new MercureTableConfigResolver(new MercureConfigResolver($hub, $metadata), $hub))
            ->resolve($this->optIns((new DataTable('t'))->ajax('/api/books'), new AsDataTable(entityClass: \stdClass::class, mercure: [
                'topics'          => ['/api/books/{id}'],
                'withCredentials' => false,
            ])));

        $this->assertFalse($config?->withCredentials);
    }

    #[Test]
    public function it_applies_attribute_options_on_top_of_the_auto_resolved_topics(): void
    {
        $configResolver = $this->createStub(MercureConfigResolver::class);
        $configResolver->method('resolveMercureConfig')->willReturn(new MercureConfig(
            topics: ['/api/books/{id}'],
            withCredentials: true,
            hubUrl: self::HUB_URL,
            protocolVersion: MercureConfig::PROTOCOL_VERSION_1_0,
        ));

        $config = (new MercureTableConfigResolver($configResolver, $this->hub(self::HUB_URL)))
            ->resolve($this->optIns((new DataTable('t'))->ajax('/api/books'), new AsDataTable(entityClass: \stdClass::class, mercure: [
                'withCredentials' => false,
                'debounceMs'      => 250,
            ])));

        $this->assertSame(['/api/books/{id}'], $config?->topics);
        $this->assertFalse($config->withCredentials);
        $this->assertSame(250, $config->debounceMs);
        $this->assertSame(self::HUB_URL, $config->hubUrl);
        $this->assertSame(MercureConfig::PROTOCOL_VERSION_1_0, $config->protocolVersion);
    }

    /**
     * @param array<string, mixed> $mercure
     */
    #[Test]
    #[DataProvider('provideTopiclessMercureOptions')]
    public function it_resolves_nothing_without_topics_when_the_auto_resolver_finds_none(array $mercure): void
    {
        $configResolver = $this->createStub(MercureConfigResolver::class);
        $configResolver->method('resolveMercureConfig')->willReturn(null);

        $resolver = new MercureTableConfigResolver($configResolver, $this->hub(self::HUB_URL));

        $this->assertNull($resolver->resolve($this->optIns((new DataTable('t'))->ajax('/api/books'), new AsDataTable(entityClass: \stdClass::class, mercure: $mercure))));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideTopiclessMercureOptions(): iterable
    {
        yield 'empty array' => [[]];

        yield 'subscription options only' => [['debounceMs' => 250, 'withCredentials' => true]];
    }

    /**
     * @param \Closure(DataTable): DataTable $configure
     */
    #[Test]
    #[DataProvider('provideCasesSkippingMercure')]
    public function it_skips_mercure_without_consulting_the_auto_resolver(AsDataTable $attribute, \Closure $configure): void
    {
        $configResolver = $this->createMock(MercureConfigResolver::class);
        $configResolver->expects($this->never())->method('resolveMercureConfig');

        $resolver = new MercureTableConfigResolver($configResolver, $this->hub(self::HUB_URL));

        $this->assertNull($resolver->resolve($this->optIns($configure(new DataTable('t')), $attribute)));
    }

    /**
     * @return iterable<string, array{AsDataTable, \Closure(DataTable): DataTable}>
     */
    public static function provideCasesSkippingMercure(): iterable
    {
        yield 'attribute mercure is false' => [
            new AsDataTable(entityClass: \stdClass::class, mercure: false),
            static fn (DataTable $table): DataTable => $table,
        ];

        yield 'client side data without ajax' => [
            new AsDataTable(entityClass: \stdClass::class, mercure: true),
            static fn (DataTable $table): DataTable => $table->data([['id' => 1]]),
        ];
    }

    #[Test]
    public function it_resolves_nothing_when_the_auto_resolver_finds_no_config_for_an_ajax_table(): void
    {
        $configResolver = $this->createStub(MercureConfigResolver::class);
        $configResolver->method('resolveMercureConfig')->willReturn(null);

        $resolver = new MercureTableConfigResolver($configResolver, $this->hub(self::HUB_URL));

        $this->assertNull($resolver->resolve($this->optIns((new DataTable('t'))->ajax('/api/books'), new AsDataTable(entityClass: \stdClass::class, mercure: true))));
    }

    #[Test]
    public function it_resolves_nothing_for_a_table_without_mercure(): void
    {
        $this->assertNull((new MercureTableConfigResolver())->resolve($this->optIns(new DataTable('t'), null)));
    }

    #[Test]
    public function it_throws_when_manual_mercure_has_no_resolvable_hub_url(): void
    {
        $table = new DataTable('t');
        $table->mercure(topics: ['/existing']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Mercure hub URL could not be resolved');

        (new MercureTableConfigResolver(hubUrlResolver: $this->hub(null)))->resolve($this->optIns($table, null));
    }

    #[Test]
    public function it_throws_when_mercure_is_requested_without_a_hub(): void
    {
        $table = new DataTable('t');
        $table->mercure(topics: ['/existing']);

        $this->expectException(\LogicException::class);

        (new MercureTableConfigResolver())->resolve($this->optIns($table, null));
    }

    #[Test]
    public function it_names_the_attribute_and_the_class_for_an_unknown_mercure_option(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown mercure option(s) "topic" declared on #[AsDataTable] for "stdClass". Supported options are "topics", "withCredentials" and "debounceMs".');

        (new MercureTableConfigResolver())->resolve($this->optIns((new DataTable('t'))->ajax('/api/books'), new AsDataTable(entityClass: \stdClass::class, mercure: [
            'topic' => 'https://example.com/books',
        ])));
    }

    /**
     * @param array<string, mixed> $mercure
     */
    #[Test]
    #[DataProvider('provideInvalidMercureOptions')]
    public function it_rejects_an_invalid_mercure_option(string $expectedMessage, array $mercure): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new MercureTableConfigResolver())->resolve($this->optIns((new DataTable('t'))->ajax('/api/books'), new AsDataTable(entityClass: \stdClass::class, mercure: $mercure)));
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

    private function optIns(DataTable $table, ?AsDataTable $attribute): ResolvedOptIns
    {
        return ResolvedOptIns::resolve($table, $attribute);
    }

    private function hub(?string $hubUrl, string $protocolVersion = MercureConfig::PROTOCOL_VERSION_0_X): MercureHubUrlResolver
    {
        $hub = $this->createStub(MercureHubUrlResolver::class);
        $hub->method('resolveHubUrl')->willReturn($hubUrl);
        $hub->method('resolveProtocolVersion')->willReturn($protocolVersion);

        return $hub;
    }
}
