<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Twig;

use Pentiminax\UX\DataTables\Tests\Support\BootsTwigKernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * The templates stay in the bundle, and the application overrides them from its own bundle template directory.
 *
 * @internal
 */
final class TemplateOverrideTest extends TestCase
{
    use BootsTwigKernel;

    private string $overrides;

    #[Test]
    public function it_renders_an_application_override_of_a_bundle_template(): void
    {
        $this->overrides = sys_get_temp_dir().'/ux-datatables-override-'.bin2hex(random_bytes(4));
        mkdir($this->overrides.'/modal/datatables', 0o775, true);
        file_put_contents($this->overrides.'/modal/datatables/_form_body.html.twig', '<p>overridden body</p>');

        /** @var Environment $twig */
        $twig   = $this->container->get('twig');
        $loader = $twig->getLoader();
        $loader = $loader instanceof ChainLoader ? $loader->getLoaders()[0] : $loader;

        $this->assertInstanceOf(FilesystemLoader::class, $loader);

        $this->assertStringContainsString('form_start', $twig->getLoader()->getSourceContext('@PentiminaxDataTables/modal/datatables/_form_body.html.twig')->getCode());

        $loader->prependPath($this->overrides, 'PentiminaxDataTables');

        $this->assertSame('<p>overridden body</p>', $twig->render('@PentiminaxDataTables/modal/datatables/_form_body.html.twig'));

        (new Filesystem())->remove($this->overrides);
    }
}
