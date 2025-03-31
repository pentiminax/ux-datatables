<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;

readonly class ButtonsExtension implements ExtensionInterface
{
    /**
     * @param ButtonType[] $buttons
     */
    public function __construct(
        private array $buttons
    ) {
    }

    public function getKey(): string
    {
        return 'layout';
    }

    public function toArray(): array
    {
        $buttons = [];

        foreach ($this->buttons as $button) {
            $buttons[] = $button->value;
        }

        return [
            'topStart' => [
                'buttons' => $buttons
            ]
        ];
    }
}