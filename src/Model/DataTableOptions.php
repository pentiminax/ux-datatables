<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\Options\LayoutOption;

class DataTableOptions implements \ArrayAccess
{
    private array $options;

    public function __construct(array $options = [])
    {
        $options = $this->handleLanguageOption($options);

        $this->options = $options;

        $this->handleLayoutOption();
    }

    public function addColumn(ColumnInterface $column): void
    {
        $this->options['columns'][] = $column->jsonSerialize();
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

    private function handleLayoutOption(): void
    {
        /** @var ?LayoutOption|array $layoutOption */
        $layoutOption = $this->options['layout'] ?? null;

        if (\is_array($layoutOption)) {
            $this->options['layout'] = $layoutOption;
        } elseif ($layoutOption instanceof LayoutOption) {
            $this->options['layout'] = $layoutOption->jsonSerialize();
        }
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
