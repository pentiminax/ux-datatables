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

            $this->addButton($button instanceof Button ? $button : Button::fromType($button));
        }
    }

    public function getKey(): string
    {
        return 'buttons';
    }

    public function hasServerExportButton(): bool
    {
        return null !== $this->findServerExportButton(null);
    }

    /**
     * The button a given export key belongs to, or the first server-side export button when the
     * request carries no key.
     */
    public function findServerExportButton(?string $exportKey): ?Button
    {
        $matches = iterator_to_array($this->serverExportButtons(), false);
        if ([] === $matches) {
            return null;
        }

        if (null === $exportKey || '' === $exportKey) {
            return $matches[0];
        }

        foreach ($matches as $button) {
            if ($button->getExportKey() === $exportKey) {
                return $button;
            }
        }

        return null;
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
        return $this->addButton(Button::colVis());
    }

    /**
     * @param list<Button|array<string, mixed>|string> $buttons
     *
     * @see Button::collection()
     */
    public function withCollectionButton(array $buttons): self
    {
        return $this->addButton(Button::collection($buttons));
    }

    /**
     * @see Button::ccSearchClear()
     */
    public function withCcSearchClearButton(): self
    {
        return $this->addButton(Button::ccSearchClear());
    }

    public function withCopyButton(): self
    {
        return $this->addButton(Button::copy());
    }

    public function withCsvButton(bool $serverSide = false): self
    {
        return $this->addButton(Button::csv($serverSide));
    }

    public function withExcelButton(bool $serverSide = false): self
    {
        return $this->addButton(Button::excel($serverSide));
    }

    public function withPdfButton(): self
    {
        return $this->addButton(Button::pdf());
    }

    public function withPrintButton(): self
    {
        return $this->addButton(Button::print());
    }

    /**
     * @see Button::custom()
     */
    public function withCustomButton(string $action): self
    {
        return $this->addButton(Button::custom($action));
    }

    private function addButton(Button $button): self
    {
        $this->assertUniqueExportKeys($button);

        $this->buttons[] = $button;

        return $this;
    }

    /**
     * Export keys address a specific button on the export endpoint, so two buttons cannot share
     * one. Renaming a duplicate silently would ignore the key the developer explicitly asked for.
     *
     * @throws \InvalidArgumentException when the incoming button reuses a registered export key
     */
    private function assertUniqueExportKeys(Button $button): void
    {
        $used = [];
        foreach ($this->serverExportButtons() as $registered) {
            $used[$registered->getExportKey()] = true;
        }

        foreach ($this->walkButtons([$button]) as $candidate) {
            if (!$candidate->isServerSideExport()) {
                continue;
            }

            $key = $candidate->getExportKey();

            if (isset($used[$key])) {
                throw new \InvalidArgumentException(\sprintf('Duplicate server-side export key "%s". Give each server-side export button its own key with Button::exportKey().', $key));
            }

            $used[$key] = true;
        }
    }

    /**
     * @return \Generator<int, Button>
     */
    private function serverExportButtons(): \Generator
    {
        foreach ($this->walkButtons($this->buttons) as $button) {
            if ($button->isServerSideExport()) {
                yield $button;
            }
        }
    }

    /**
     * @param list<Button|array<string, mixed>|string> $buttons
     *
     * @return \Generator<int, Button>
     */
    private function walkButtons(array $buttons): \Generator
    {
        foreach ($buttons as $button) {
            if (!$button instanceof Button) {
                continue;
            }

            yield $button;
            yield from $this->walkButtons($button->getChildButtons());
        }
    }
}
