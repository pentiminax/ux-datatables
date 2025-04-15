<?php

namespace Pentiminax\UX\DataTables\Model;

use ArrayAccess;
use Pentiminax\UX\DataTables\Enum\Language;

class DataTableOptions implements ArrayAccess
{
    private array $options;

    public function __construct(array $options = [])
    {
        $options = $this->handleLanguageOption($options);

        $this->options = $options;
    }

    public function addColumn(array $column): void
    {
        $this->options['columns'][] = $column;
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
        if (isset($options['language']) && is_string($options['language'])) {
            $language = $options['language'];

            unset($options['language']);

            $options['language']['url'] = Language::from($language)->getUrl();
        }

        return $options;
    }

    public function getOptions(): array
    {
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