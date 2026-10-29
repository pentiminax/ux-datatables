<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Summary;

final class Sum extends Summarizer
{
    public function getType(): SummaryType
    {
        return SummaryType::Sum;
    }
}
