<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions\ColumnControl;

use Pentiminax\UX\DataTables\Contracts\SearchListOptionsProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SearchList implements \JsonSerializable
{
    /** @var array<string, mixed> */
    private array $configuration = [];

    /** @var array<mixed>|class-string<\BackedEnum>|null */
    private array|string|null $staticOptions = null;

    private bool $hasStaticOptions = false;

    private SearchListOptionsProviderInterface|\Closure|null $ajaxOptionsProvider = null;

    private function __construct()
    {
    }

    public static function new(): static
    {
        return new static();
    }

    /**
     * @param array<mixed>|class-string<\BackedEnum> $options
     */
    public function options(array|string $options): static
    {
        if (null !== $this->ajaxOptionsProvider) {
            $this->throwMixedSources();
        }

        $this->staticOptions            = $options;
        $this->configuration['options'] = SearchListOptionsNormalizer::normalize($options);
        $this->hasStaticOptions         = true;

        return $this;
    }

    public function ajaxOptionsProvider(SearchListOptionsProviderInterface|\Closure $provider): static
    {
        if ($this->hasStaticOptions) {
            $this->throwMixedSources();
        }

        $this->ajaxOptionsProvider = $provider;

        return $this;
    }

    public function ajaxOnly(bool $enabled = true): static
    {
        $this->configuration['ajaxOnly'] = $enabled;

        return $this;
    }

    public function hidable(bool $enabled = true): static
    {
        $this->configuration['hidable'] = $enabled;

        return $this;
    }

    public function orthogonal(string $type): static
    {
        if ('' === trim($type)) {
            throw new \InvalidArgumentException('Search list orthogonal data type must not be empty.');
        }

        $this->configuration['orthogonal'] = $type;

        return $this;
    }

    public function search(bool $enabled = true): static
    {
        $this->configuration['search'] = $enabled;

        return $this;
    }

    public function select(bool $enabled = true): static
    {
        $this->configuration['select'] = $enabled;

        return $this;
    }

    public function title(string $title): static
    {
        $this->configuration['title'] = $title;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return ['extend' => 'searchList', ...$this->configuration];
    }

    /** @internal */
    public function prepareStaticOptions(?TranslatorInterface $translator): void
    {
        if (!$this->hasStaticOptions) {
            return;
        }

        $this->configuration['options'] = SearchListOptionsNormalizer::normalize($this->staticOptions, $translator);
    }

    /** @internal */
    public function getAjaxOptionsProvider(): SearchListOptionsProviderInterface|\Closure|null
    {
        return $this->ajaxOptionsProvider;
    }

    private function throwMixedSources(): never
    {
        throw new \LogicException('Search list static options and an Ajax options provider are mutually exclusive.');
    }
}
