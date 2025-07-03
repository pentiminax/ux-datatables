<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;

class ButtonsExtension implements ExtensionInterface
{
    /** @var ButtonType[] */
    private array $buttons = [];

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
        return 'buttons';
    }

    public function jsonSerialize(): array
    {
        $buttons = [];

        foreach ($this->buttons as $button) {
            if ($button === ButtonType::COLUMN_VISIBILITY) {
                $buttons[] = $button->value;

                continue;
            }

            $buttons[] = [
                'extend' => $button->value,
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ];
        }

        return $buttons;
    }
}