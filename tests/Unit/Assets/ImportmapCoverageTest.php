<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Assets;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ImportmapCoverageTest extends TestCase
{
    #[Test]
    public function it_declares_every_published_import_for_asset_mapper(): void
    {
        $root    = \dirname(__DIR__, 3);
        $package = json_decode(
            (string) file_get_contents($root.'/assets/package.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        $importmap  = $package['symfony']['importmap'];
        $specifiers = [];

        foreach ($this->publishedFiles($root.'/assets/dist') as $file) {
            preg_match_all(
                "/(?:\\bfrom|\\bimport)\\s*\\(?\\s*'([^']+)'/",
                (string) file_get_contents($file),
                $matches,
            );

            foreach ($matches[1] as $specifier) {
                if (str_starts_with($specifier, '.')) {
                    continue;
                }

                $specifiers[$specifier] = str_replace($root.'/', '', $file);
            }
        }

        self::assertNotEmpty($specifiers, 'No published import was found; assets/dist must be built.');

        foreach ($specifiers as $specifier => $file) {
            self::assertArrayHasKey(
                $specifier,
                $importmap,
                \sprintf('The import "%s" in %s must be declared in symfony.importmap.', $specifier, $file),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function publishedFiles(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        $files    = [];

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && 'js' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
