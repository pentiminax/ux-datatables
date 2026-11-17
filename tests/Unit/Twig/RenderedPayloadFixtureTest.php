<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Twig;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\PayloadFixtureDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Pins the payload the PHP side renders for the Stimulus controller. The same file is loaded by
 * assets/test/renderedPayloadFixture.test.ts, so the two sides cannot drift apart silently.
 * Regenerate it with UPDATE_PAYLOAD_FIXTURE=1 after an intended payload change.
 *
 * @internal
 */
final class RenderedPayloadFixtureTest extends KernelTestCase
{
    use CollectsDeprecations;

    private const FIXTURE = __DIR__.'/../../Fixtures/rendered-payload.json';

    #[Test]
    public function it_renders_the_pinned_payload_with_the_bare_fluent_api_and_no_deprecation(): void
    {
        static::bootKernel();
        $container = static::getContainer();
        $table     = new PayloadFixtureDataTable();
        $table->setDataTableInfrastructure($container->get('datatables.infrastructure'));

        $rendered     = '';
        $deprecations = $this->deprecationsOf(function () use ($table, $container, &$rendered): void {
            $rendered = $container->get('datatables.twig_extension')->renderDataTable($table);
        });

        $this->assertSame([], array_values(array_filter($deprecations, static fn (string $m): bool => str_contains($m, 'pentiminax/ux-datatables'))));

        $dom = new \DOMDocument();
        $dom->loadHTML($rendered);
        $attribute = $dom->getElementsByTagName('table')->item(0)->getAttribute('data-pentiminax--ux-datatables--datatable-view-value');
        $payload   = json_decode(html_entity_decode($attribute), true, 512, \JSON_THROW_ON_ERROR);

        if ('1' === getenv('UPDATE_PAYLOAD_FIXTURE')) {
            file_put_contents(self::FIXTURE, json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n");
        }

        $this->assertSame(
            json_decode((string) file_get_contents(self::FIXTURE), true, 512, \JSON_THROW_ON_ERROR),
            $payload,
        );
    }

    /**
     * @param \Closure(ContainerInterface): AbstractDataTable $createTable
     */
    #[Test]
    #[DataProvider('provideRenderedTables')]
    public function it_renders_twice_identically_without_writing_to_the_configured_table(\Closure $createTable): void
    {
        static::bootKernel();
        $container = static::getContainer();
        $table     = $createTable($container);
        $extension = $container->get('datatables.twig_extension');

        $configured = $this->describe($table->getConfiguredDataTable());
        $first      = $extension->renderDataTable($table);

        $this->assertSame($configured, $this->describe($table->getConfiguredDataTable()));
        $this->assertSame($first, $extension->renderDataTable($table));
    }

    /**
     * @return iterable<string, array{\Closure(ContainerInterface): AbstractDataTable}>
     */
    public static function provideRenderedTables(): iterable
    {
        yield 'inline rows, bulk and row actions, filters' => [static function (ContainerInterface $container): AbstractDataTable {
            $table = new PayloadFixtureDataTable();
            $table->setDataTableInfrastructure($container->get('datatables.infrastructure'));

            return $table;
        }];

        yield 'server-side' => [static fn (ContainerInterface $container): AbstractDataTable => $container->get(HarnessServerSideDataTable::class)];
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(DataTable $table): array
    {
        return [
            'options'    => $table->getOptions(),
            'bundle'     => array_map($table->getBundleOption(...), ['apiPlatform', 'pollInterval', 'urlState', 'styleFramework', 'exportUrl', 'showHeaderResetButton']),
            'extensions' => $table->getExtensions(),
            'mercure'    => $table->getMercureConfig()?->topics,
            'editModal'  => [$table->getEditModalTemplate(), $table->getEditModalAdapter()],
            'filters'    => $table->getFilters()?->jsonSerialize(),
        ];
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
