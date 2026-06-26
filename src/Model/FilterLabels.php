<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Optional overrides for the filter bar chrome strings (toggle title, reset and
 * apply buttons, the empty-select placeholder).
 *
 * Values are resolved lazily at render time by the RenderingPreparer:
 *  - When a value is provided by the developer it may be a plain string or a
 *    translation key and is passed through the translator in the default domain.
 *  - When a value is left null the built-in bundle default is used: the matching
 *    key from the "DataTables" translation domain is resolved instead, so the
 *    filter bar is localised out of the box (the bundle ships en/fr catalogs and
 *    apps can add their own). Override it per-table via Filters::labels().
 */
final class FilterLabels implements \JsonSerializable
{
    /**
     * Translation domain holding the built-in default labels shipped by the bundle.
     */
    public const string DOMAIN = 'DataTables';

    /**
     * Default translation keys (resolved in self::DOMAIN) used when the developer
     * does not override a given label.
     *
     * @var array<string, string>
     */
    private const array DEFAULT_KEYS = [
        'title' => 'filter.bar.title',
        'reset' => 'filter.bar.reset',
        'apply' => 'filter.bar.apply',
        'all'   => 'filter.bar.all',
    ];

    public function __construct(
        public ?string $title = null,
        public ?string $reset = null,
        public ?string $apply = null,
        public ?string $all = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->title
            && null === $this->reset
            && null === $this->apply
            && null === $this->all;
    }

    public function translate(TranslatorInterface $translator, ?string $locale = null): void
    {
        $this->title = $this->resolve($translator, 'title', $this->title, $locale);
        $this->reset = $this->resolve($translator, 'reset', $this->reset, $locale);
        $this->apply = $this->resolve($translator, 'apply', $this->apply, $locale);
        $this->all   = $this->resolve($translator, 'all', $this->all, $locale);
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'title' => $this->title,
            'reset' => $this->reset,
            'apply' => $this->apply,
            'all'   => $this->all,
        ], static fn (?string $value): bool => null !== $value);
    }

    /**
     * Resolve a single label: a developer-provided value is translated in the
     * default domain, otherwise the built-in default is resolved in self::DOMAIN.
     */
    private function resolve(TranslatorInterface $translator, string $key, ?string $value, ?string $locale): string
    {
        if (null !== $value) {
            return $translator->trans($value, locale: $locale);
        }

        return $translator->trans(self::DEFAULT_KEYS[$key], domain: self::DOMAIN, locale: $locale);
    }
}
