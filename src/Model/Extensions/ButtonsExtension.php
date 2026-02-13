<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;

final class ButtonsExtension extends AbstractExtension
{
    /** @var ButtonType[] */
    private array $buttons = [];

    /**
     * @param ButtonType[]|string[] $buttons
     */
    public function __construct(
        array $buttons,
    ) {
        foreach ($buttons as $button) {
            if (\is_string($button)) {
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
            if (ButtonType::COLUMN_VISIBILITY === $button) {
                $buttons[] = $button->value;

                continue;
            }

            $buttons[] = [
                'extend'        => $button->value,
                'exportOptions' => [
                    'columns' => ':visible:not(.not-exportable)',
                ],
            ];
        }

        return $buttons;
    }

    public function withColVisButton(): self
    {
        $this->buttons[] = ButtonType::COLUMN_VISIBILITY;

        return $this;
    }

    public function withCopyButton(): self
    {
        $this->buttons[] = ButtonType::COPY;

        return $this;
    }

    public function withCsvButton(): self
    {
        $this->buttons[] = ButtonType::CSV;

        return $this;
    }

    public function withExcelButton(): self
    {
        $this->buttons[] = ButtonType::EXCEL;

        return $this;
    }

    public function withPdfButton(): self
    {
        $this->buttons[] = ButtonType::PDF;

        return $this;
    }

    public function withPrintButton(): self
    {
        $this->buttons[] = ButtonType::PRINT;

        return $this;
    }
}
