<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Install;

use Pentiminax\UX\DataTables\Install\InstallStyle;
use Pentiminax\UX\DataTables\Install\PackageCatalog;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The installer writes the specifiers the 1.x built-in loaders import; parsing the TypeScript
 * source catches a package added or renamed on one side only.
 *
 * @internal
 */
#[CoversNothing]
final class PackageCatalogConsistencyTest extends TestCase
{
    /**
     * @return iterable<string, array{InstallStyle}>
     */
    public static function styles(): iterable
    {
        yield 'dt' => [InstallStyle::DataTables];
        yield 'bs5' => [InstallStyle::Bootstrap5];
    }

    #[Test]
    #[DataProvider('styles')]
    public function it_lists_the_specifiers_of_the_builtin_loaders(InstallStyle $style): void
    {
        $source    = $this->read('assets/src/functions/builtinPackageLoaders.ts');
        $framework = $style->framework()->value;

        preg_match('/const frameworkLoaders.*?\n}\n/s', $source, $block);
        $this->assertNotEmpty($block, 'Could not find frameworkLoaders in builtinPackageLoaders.ts.');

        $expected = [];
        preg_match_all('/^    (\w+): \{\n(.*?)^    \},/ms', $block[0], $packages, \PREG_SET_ORDER);

        foreach ($packages as [, $package, $body]) {
            $segments = preg_split('/^        (dt|bs|bs4|bs5): /m', $body, -1, \PREG_SPLIT_DELIM_CAPTURE);
            for ($i = 1; $i < \count($segments); $i += 2) {
                if ($segments[$i] === $framework) {
                    preg_match_all("/import\('([^']+)'\)/", $segments[$i + 1], $imports);
                    $expected[$package] = $imports[1];
                }
            }
        }

        preg_match('/const sharedLoaders.*?\n}\n/s', $source, $shared);
        $segments = preg_split('/^    (\w+): /m', $shared[0], -1, \PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 1; $i < \count($segments); $i += 2) {
            preg_match_all("/import\('([^']+)'\)/", $segments[$i + 1], $imports);
            $expected[$segments[$i]] = $imports[1];
        }

        $this->assertCount(16, $expected, 'Could not parse every package out of builtinPackageLoaders.ts.');

        $actual = [];
        foreach (PackageCatalog::registered(PackageCatalog::selectable(), InstallStyle::Bootstrap5) as $package) {
            $actual[$package] = PackageCatalog::specifiers($package, $style);
        }

        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_lists_the_frontend_extension_names(): void
    {
        $source = $this->read('assets/src/functions/packageLoaders.ts');

        preg_match('/EXTENSION_NAMES = \[(.*?)\]/s', $source, $block);
        preg_match_all("/'(\w+)'/", $block[1] ?? '', $names);

        $this->assertSame($names[1], PackageCatalog::EXTENSIONS);
    }

    private function read(string $path): string
    {
        $source = file_get_contents(\dirname(__DIR__, 3).'/'.$path);
        $this->assertNotFalse($source, "Could not read {$path}.");

        return $source;
    }
}
