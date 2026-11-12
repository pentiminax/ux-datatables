<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;

class DataTableOptions
{
    private array $options;

    public function __construct(array $options = [])
    {
        $options = $this->handleLanguageOption($options);

        $this->options = $options;
    }

    public function language(Language $language): static
    {
        $this->options['language']['url'] = $language->getUrl();

        return $this;
    }

    /**
     * @deprecated since 1.6, use language() instead. Removed in 2.0.
     */
    public function setLanguage(Language $language): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "language()" instead.', __METHOD__);

        return $this->language($language);
    }

    public function search(string $search): static
    {
        $this->options['search']['search'] = $search;

        return $this;
    }

    /**
     * @deprecated since 1.6, use search() instead. Removed in 2.0.
     */
    public function setSearch(string $search): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "search()" instead.', __METHOD__);

        return $this->search($search);
    }

    /**
     * Set an arbitrary DataTables.net option.
     *
     * Escape hatch for options the bundle does not expose through a typed
     * setter. Prefer the dedicated builder methods on {@see DataTable} when
     * available.
     */
    public function set(string $name, mixed $value): static
    {
        $this->options[$name] = $value;

        return $this;
    }

    public function get(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function remove(string $name): static
    {
        unset($this->options[$name]);

        return $this;
    }

    public function getOptions(): array
    {
        $this->handleLayoutOption();

        return $this->options;
    }

    private function handleLanguageOption(array $options): array
    {
        if (isset($options['language']) && \is_string($options['language'])) {
            $language = $options['language'];

            unset($options['language']);

            $options['language']['url'] = Language::from($language)->getUrl();
        }

        return $options;
    }

    private function handleLayoutOption(): void
    {
        $layout = $this->options['layout'] ?? null;

        if (!\is_array($layout)) {
            return;
        }

        $this->options['layout'] = array_map(
            fn ($value) => $this->normalizeLayoutValue($value),
            $layout
        );
    }

    private function normalizeLayoutValue(mixed $value): mixed
    {
        if ($value instanceof Feature) {
            return $value->value;
        }

        if (\is_array($value)) {
            return array_map(fn ($v) => $this->normalizeLayoutValue($v), $value);
        }

        return $value;
    }
}
