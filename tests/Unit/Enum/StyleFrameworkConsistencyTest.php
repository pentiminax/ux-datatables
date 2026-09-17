<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Enum;

use Pentiminax\UX\DataTables\Enum\StyleFramework;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * StyleFramework's cases are serialized into the payload and checked by the frontend's
 * isStyleFramework() (assets/src/types/styleFramework.ts) against its own STYLE_FRAMEWORKS
 * registry before detectStyleFramework() ever runs. The frontend's own tests derive their
 * inputs from STYLE_FRAMEWORKS itself, so they stay green even when this enum drifts from it
 * -- a PHP-only case, or a value that no longer matches its frontend counterpart, makes
 * isStyleFramework() silently reject the declared framework and fall back to autodetection
 * instead of failing loudly. Parsing the frontend source directly is what actually catches
 * that, rather than trusting the two declarations to stay in sync by convention.
 *
 * @internal
 */
#[CoversNothing]
final class StyleFrameworkConsistencyTest extends TestCase
{
    #[Test]
    public function it_matches_the_frontend_style_framework_registry(): void
    {
        $phpValues = array_map(
            static fn (StyleFramework $case): string => $case->value,
            StyleFramework::cases(),
        );

        $tsPath = \dirname(__DIR__, 3).'/assets/src/types/styleFramework.ts';
        $source = file_get_contents($tsPath);

        $this->assertNotFalse($source, "Could not read {$tsPath}.");

        preg_match_all("/key:\s*'([a-z0-9]+)'/", $source, $matches);
        $frontendValues = $matches[1];

        $this->assertNotEmpty(
            $frontendValues,
            'Could not parse any STYLE_FRAMEWORKS keys out of styleFramework.ts -- the regex '
                .'above may no longer match its current shape.',
        );

        sort($phpValues);
        sort($frontendValues);

        $this->assertSame(
            $frontendValues,
            $phpValues,
            'StyleFramework (src/Enum/StyleFramework.php) and the frontend STYLE_FRAMEWORKS '
                .'registry (assets/src/types/styleFramework.ts) have drifted apart -- add or '
                .'change a case in both places together.',
        );
    }
}
