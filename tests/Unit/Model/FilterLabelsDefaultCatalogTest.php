<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Model\FilterLabels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Verifies the bundle ships a valid "DataTables" catalog so the filter bar
 * chrome strings (including the "All" placeholder) are localized out of the box.
 *
 * @internal
 */
#[CoversClass(FilterLabels::class)]
final class FilterLabelsDefaultCatalogTest extends TestCase
{
    #[Test]
    public function it_resolves_the_french_defaults_from_the_bundle_catalog(): void
    {
        $translator = $this->buildTranslator('fr');

        $labels = new FilterLabels();

        $this->assertSame(
            [
                'title' => 'Filtres',
                'reset' => 'Réinitialiser',
                'apply' => 'Appliquer les filtres',
                'all'   => 'Tous',
            ],
            $labels->toTranslatedArray($translator, 'fr'),
        );
        $this->assertSame([], $labels->jsonSerialize());
    }

    #[Test]
    public function it_resolves_the_english_defaults_from_the_bundle_catalog(): void
    {
        $translator = $this->buildTranslator('en');

        $labels = new FilterLabels();

        $this->assertSame(
            [
                'title' => 'Filters',
                'reset' => 'Reset',
                'apply' => 'Apply filters',
                'all'   => 'All',
            ],
            $labels->toTranslatedArray($translator, 'en'),
        );
        $this->assertSame([], $labels->jsonSerialize());
    }

    private function buildTranslator(string $locale): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('xlf', new XliffFileLoader());

        $dir = \dirname(__DIR__, 3).'/translations';
        foreach (['en', 'fr'] as $catalogLocale) {
            $translator->addResource(
                'xlf',
                \sprintf('%s/DataTables.%s.xlf', $dir, $catalogLocale),
                $catalogLocale,
                FilterLabels::DOMAIN,
            );
        }

        return $translator;
    }
}
