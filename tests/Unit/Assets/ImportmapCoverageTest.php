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
    public function it_declares_every_buttons_loader_import_for_asset_mapper(): void
    {
        $root    = \dirname(__DIR__, 3);
        $package = json_decode(
            (string) file_get_contents($root.'/assets/package.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $loader = (string) file_get_contents($root.'/assets/dist/functions/loadButtonsLibrary.js');

        preg_match_all("/import\\('([^']+)'\\)/", $loader, $matches);

        foreach (array_unique($matches[1]) as $specifier) {
            self::assertArrayHasKey(
                $specifier,
                $package['symfony']['importmap'],
                \sprintf('The Buttons loader import "%s" must be declared in symfony.importmap.', $specifier),
            );
        }
    }
}
