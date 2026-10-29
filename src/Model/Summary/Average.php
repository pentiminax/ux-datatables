<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Summary;

final class Average extends Summarizer
{
    public function getType(): SummaryType
    {
        return SummaryType::Average;
    }
}
