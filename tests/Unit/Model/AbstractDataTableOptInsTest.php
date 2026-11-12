<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
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

    #[Test]
    public function it_keeps_an_explicit_fluent_opt_out_on_an_ajax_table_the_attribute_opts_into(): void
    {
        $payload = [];
        $notices = $this->deprecationsOf(function () use (&$payload): void {
            $payload = $this->render(AttributeAjaxFluentOptOutFixture::class, '/plain');
        });

        $this->assertFalse($payload['apiPlatform']);
        $this->assertSame([], $this->matching($notices, 'API Platform'));
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

        $this->assertCount(1, $this->matching($notices, 'will win in 2.0'));
        $this->assertTrue($payload['apiPlatform'], 'The 1.x precedence is unchanged in 1.6.');
    }

    #[Test]
    #[DataProvider('provideOptedInSurfaces')]
    public function it_answers_not_found_after_rendering_whichever_surface_opted_in(string $tableClass): void
    {
        $table = new $tableClass(null);

        $this->deprecationsOf(static fn () => $table->getDataTable());
        $table->handleRequest(new Request(query: ['draw' => 1, 'start' => 0, 'length' => 10]));

        $this->expectException(NotFoundHttpException::class);

        $table->getResponse();
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
     * @return array<string, mixed>
     */
    private function render(string $tableClass, ?string $collectionUrl): array
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

        $options = (new $tableClass($urlResolver))->getDataTable()->getOptions();
        unset($options['columns']);

        return $options;
    }
}

abstract class OptInFixture extends AbstractDataTable
{
    public function __construct(?ApiResourceCollectionUrlResolver $urlResolver)
    {
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
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
