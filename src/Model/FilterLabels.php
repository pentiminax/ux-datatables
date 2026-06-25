<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Optional overrides for the filter bar chrome strings (toggle title, reset and
 * apply buttons, the empty-select placeholder).
 *
 * Values are resolved lazily: they may be plain strings or translation keys and
 * are passed through the translator by the RenderingPreparer at render time,
 * mirroring how column titles and filter option labels are handled. When a value
 * is left null the frontend falls back to its built-in English default.
 */
final class FilterLabels implements \JsonSerializable
{
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
        if (null !== $this->title) {
            $this->title = $translator->trans($this->title, locale: $locale);
        }

        if (null !== $this->reset) {
            $this->reset = $translator->trans($this->reset, locale: $locale);
        }

        if (null !== $this->apply) {
            $this->apply = $translator->trans($this->apply, locale: $locale);
        }

        if (null !== $this->all) {
            $this->all = $translator->trans($this->all, locale: $locale);
        }
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
}
