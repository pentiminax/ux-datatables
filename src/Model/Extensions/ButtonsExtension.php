<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Contracts\LayoutAwareExtensionInterface;
use Pentiminax\UX\DataTables\Enum\ButtonType;

final class ButtonsExtension extends AbstractExtension implements LayoutAwareExtensionInterface
{
    /** @var Button[] */
    private array $buttons = [];

    /**
     * @param ButtonType[]|string[]|Button[] $buttons
     */
    public function __construct(
        array $buttons,
    ) {
        foreach ($buttons as $button) {
            if (\is_string($button)) {
                $button = ButtonType::from($button);
            }

            $this->buttons[] = $button instanceof Button ? $button : Button::fromType($button);
        }
    }

    public function getKey(): string
    {
        return 'buttons';
    }

    public function jsonSerialize(): array
    {
        return array_map(
            static fn (Button $button): array|string => $button->jsonSerialize(),
            $this->buttons,
        );
    }

    public function withColVisButton(): self
    {
        $this->buttons[] = Button::colVis();

        return $this;
    }

    public function withCopyButton(): self
    {
        $this->buttons[] = Button::copy();

        return $this;
    }

    public function withCsvButton(): self
    {
        $this->buttons[] = Button::csv();

        return $this;
    }

    public function withExcelButton(): self
    {
        $this->buttons[] = Button::excel();

        return $this;
    }

    public function withPdfButton(): self
    {
        $this->buttons[] = Button::pdf();

        return $this;
    }

    public function withPrintButton(): self
    {
        $this->buttons[] = Button::print();

        return $this;
    }
}
