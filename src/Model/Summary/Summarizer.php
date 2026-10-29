<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Summary;

/**
 * A footer figure computed over every row matching the current search and filters.
 *
 * A column declares at most one summarizer per type through
 * {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::summarize()}.
 */
abstract class Summarizer
{
    final public function __construct(private readonly ?string $label = null)
    {
    }

    public static function new(?string $label = null): static
    {
        return new static($label);
    }

    abstract public function getType(): SummaryType;

    /**
     * The custom label, or null to use the translated default of the summary type.
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }
}
