<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Summary;

final class Count extends Summarizer
{
    public function getType(): SummaryType
    {
        return SummaryType::Count;
    }
}
