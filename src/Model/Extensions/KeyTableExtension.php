<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

class KeyTableExtension extends AbstractExtension
{
    /**
     * @param array{0: int, 1: int}|null $focus a [row, column] pair to focus on load, or null for none
     * @param list<int|string>|null      $keys  key codes to listen for, or null for every key
     */
    public function __construct(
        private readonly bool $blurable = true,
        private readonly string $className = 'focus',
        private readonly bool $clipboard = true,
        private readonly string $clipboardOrthogonal = 'display',
        private readonly string $columns = '',
        private readonly bool $editOnFocus = false,
        private readonly ?array $focus = null,
        private readonly ?array $keys = null,
        private readonly ?int $tabIndex = null,
    ) {
    }

    public function getKey(): string
    {
        return 'keys';
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'blurable'            => $this->blurable,
            'className'           => $this->className,
            'clipboard'           => $this->clipboard,
            'clipboardOrthogonal' => $this->clipboardOrthogonal,
            'columns'             => $this->columns,
            'editOnFocus'         => $this->editOnFocus,
            'focus'               => $this->focus,
            'keys'                => $this->keys,
            'tabIndex'            => $this->tabIndex,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
