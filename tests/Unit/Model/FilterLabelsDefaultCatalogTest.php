<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Model\FilterLabels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @param array<string, string> $expected
     */
    #[Test]
    #[DataProvider('provideTranslatedLabels')]
    public function it_translates_labels_through_the_bundle_catalog(string $locale, FilterLabels $labels, array $expected): void
    {
        $this->assertSame($expected, $labels->toTranslatedArray($this->buildTranslator($locale), $locale));
    }

    /**
     * @return iterable<string, array{string, FilterLabels, array<string, string>}>
     */
    public static function provideTranslatedLabels(): iterable
    {
        yield 'french defaults' => ['fr', new FilterLabels(), [
            'title' => 'Filtres',
            'reset' => 'Réinitialiser',
            'apply' => 'Appliquer les filtres',
            'all'   => 'Tous',
        ]];

        yield 'english defaults' => ['en', new FilterLabels(), [
            'title' => 'Filters',
            'reset' => 'Reset',
            'apply' => 'Apply filters',
            'all'   => 'All',
        ]];

        yield 'explicit labels override the catalog defaults' => ['en', new FilterLabels(title: 'My filters'), [
            'title' => 'My filters',
            'reset' => 'Reset',
            'apply' => 'Apply filters',
            'all'   => 'All',
        ]];
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
