<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Symfony\Contracts\Translation\TranslatorInterface;

final class FilterLabels implements \JsonSerializable
{
    public const string DOMAIN = 'DataTables';

    /**
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

    /**
     * @return array<string, string>
     */
    public function toTranslatedArray(TranslatorInterface $translator, ?string $locale = null): array
    {
        return [
            'title' => $this->resolve($translator, 'title', $this->title, $locale),
            'reset' => $this->resolve($translator, 'reset', $this->reset, $locale),
            'apply' => $this->resolve($translator, 'apply', $this->apply, $locale),
            'all'   => $this->resolve($translator, 'all', $this->all, $locale),
        ];
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

    private function resolve(TranslatorInterface $translator, string $key, ?string $value, ?string $locale): string
    {
        if (null !== $value) {
            return $translator->trans($value, locale: $locale);
        }

        return $translator->trans(self::DEFAULT_KEYS[$key], domain: self::DOMAIN, locale: $locale);
    }
}
