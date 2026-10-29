<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Summary;

final class Min extends Summarizer
{
    public function getType(): SummaryType
    {
        return SummaryType::Min;
    }
}
