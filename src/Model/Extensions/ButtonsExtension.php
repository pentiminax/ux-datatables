<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;

class ButtonsExtension implements ExtensionInterface
{
    /** @var ButtonType[]  */
    private array $buttons;

    /**
     * @param ButtonType[]|string[] $buttons
     */
    public function __construct(
        array $buttons
    ) {
        foreach ($buttons as $button) {
            if (is_string($button)) {
                $button = ButtonType::from($button);
            }

            $this->buttons[] = $button;
        }
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