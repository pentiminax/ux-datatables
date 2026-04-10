<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;

class DataTableOptions implements \ArrayAccess
{
    private array $options;

    public function __construct(array $options = [])
    {
        $options = $this->handleLanguageOption($options);

        $this->options = $options;
    }

    public function setLanguage(Language $language): static
    {
        $this->options['language']['url'] = $language->getUrl();

        return $this;
    }

    public function setSearch(string $search): static
    {
        $this->options['search']['search'] = $search;

        return $this;
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

    public function getOptions(): array
    {
        $this->handleLayoutOption();

        return $this->options;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->options[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->options[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->options[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->options[$offset]);
    }
}
