<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Twig;

use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\PayloadFixtureDataTable;
use Pentiminax\UX\DataTables\Tests\Kernel\TestHarnessAppKernel;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestHarnessAppKernel('test', true);
    }
}
