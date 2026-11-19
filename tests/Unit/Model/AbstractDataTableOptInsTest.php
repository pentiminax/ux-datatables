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
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use Pentiminax\UX\DataTables\Tests\Support\PreparesPayload;
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
    use PreparesPayload;
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

        yield 'ajax url read with the Hydra adapter' => [AttributeAjaxOptInFixture::class, FluentAjaxOptInFixture::class];
    }

    /**
     * @param class-string<OptInFixture> $tableClass
     * @param array<string, mixed>       $source
     */
    #[Test]
    #[DataProvider('provideFluentOptOuts')]
    public function it_lets_a_fluent_opt_out_win_over_the_attribute(string $tableClass, array $source): void
    {
        $detector = $this->createMock(ColumnAutoDetector::class);
        $detector->expects($this->never())->method('detectColumns');

        $payload = [];
        $notices = $this->deprecationsOf(function () use (&$payload, $tableClass, $detector): void {
            $payload = $this->render($tableClass, '/api/books', $detector);
        });

        $this->assertFalse($payload['apiPlatform']);
        foreach ($source as $option => $value) {
            $this->assertSame($value, $payload[$option]);
        }
        $this->assertSame([], $this->apiPlatformNotices($notices));
    }

    public static function provideFluentOptOuts(): iterable
    {
        yield 'server side' => [AttributeOptInFluentOptOutFixture::class, ['serverSide' => true]];

        yield 'data(), auto-detected columns' => [AttributeInlineFluentOptOutAutoDetectedFixture::class, ['data' => [['id' => 1]]]];

        yield 'data(), explicit columns' => [AttributeInlineFluentOptOutFixture::class, ['data' => [['id' => 1]]]];

        yield 'ajax(), auto-detected columns' => [AttributeAjaxFluentOptOutAutoDetectedFixture::class, ['ajax' => ['type' => 'GET', 'url' => '/plain']]];

        yield 'ajax(), explicit columns' => [AttributeAjaxFluentOptOutFixture::class, ['ajax' => ['type' => 'GET', 'url' => '/plain']]];
    }

    #[Test]
    public function it_keeps_the_mode_resolved_before_rendering_for_the_ajax_request_on_the_same_table(): void
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
        $table    = new FluentOptInFixture($resolver);
        $prepared = $table->prepareRendering();

        $this->assertSame(DataSourceMode::ApiPlatform, $prepared->dataSourceMode);
        $this->assertNotNull(self::optionOf($prepared, 'ajax'));
        $this->assertNull($table->getConfiguredDataTable()->getOption('ajax'));
        $this->assertSame(DataSourceMode::ApiPlatform, $table->getResolvedOptIns()->dataSourceMode);
    }

    #[Test]
    public function it_keeps_the_opt_ins_resolved_when_the_table_was_configured(): void
    {
        $table = new AttributeOptInFluentOptOutFixture(null);
        $table->getConfiguredDataTable()->editModalTemplate('custom/modal.html.twig');

        $this->assertNotSame('custom/modal.html.twig', $table->getResolvedOptIns()->editModalTemplate);
    }

    #[Test]
    #[DataProvider('provideInlineSurfaces')]
    public function it_refuses_inline_data_with_an_api_platform_opt_in(string $tableClass): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('combines data() with an API Platform opt-in');

        $this->render($tableClass, '/api/books');
    }

    public static function provideInlineSurfaces(): iterable
    {
        yield 'attribute' => [AttributeInlineOptInFixture::class];

        yield 'fluent' => [FluentInlineOptInFixture::class];
    }

    #[Test]
    #[DataProvider('provideOptedInSurfaces')]
    public function it_refuses_an_opt_in_without_a_collection(string $tableClass): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('exposes no collection operation');

        $this->render($tableClass, null);
    }

    public static function provideOptedInSurfaces(): iterable
    {
        yield 'attribute' => [AttributeOptInFixture::class];

        yield 'fluent' => [FluentOptInFixture::class];
    }

    #[Test]
    #[DataProvider('provideOptedInSurfaces')]
    public function it_answers_not_found_whichever_surface_opted_in(string $tableClass): void
    {
        $table = new $tableClass(null);
        $table->handleDataTableRequest(new Request(query: ['draw' => 1, 'start' => 0, 'length' => 10]));

        $this->expectException(NotFoundHttpException::class);

        $table->createDataTableResponse();
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

        $options = (new $tableClass($urlResolver, $detector))->prepareRendering()->getView();
        unset($options['columns']);

        return $options;
    }
}

abstract class OptInFixture extends AbstractDataTable
{
    public function __construct(?ApiResourceCollectionUrlResolver $urlResolver, ?ColumnAutoDetector $detector = null)
    {
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            columnResolver: new ColumnResolver(new AuthorizationChecker(), columnAutoDetector: $detector),
            renderingPreparer: new RenderingPreparer(new AuthorizationChecker(), new ColumnResolver(new AuthorizationChecker()), urlResolver: $urlResolver),
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
        return $table->apiPlatform(false)->serverSide();
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
