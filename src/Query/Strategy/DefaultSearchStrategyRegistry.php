<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;

class DefaultSearchStrategyRegistry extends SearchStrategyRegistry
{
    public function __construct()
    {
        parent::__construct([
            new ContainsSearchStrategy(),
            new NullnessSearchStrategy(),
            new ComparisonSearchStrategy(ColumnControlLogic::Ends),
            new ComparisonSearchStrategy(ColumnControlLogic::Equal),
            new ComparisonSearchStrategy(ColumnControlLogic::Greater),
            new ComparisonSearchStrategy(ColumnControlLogic::GreaterOrEqual),
            new InListSearchStrategy(),
            new ComparisonSearchStrategy(ColumnControlLogic::Less),
            new ComparisonSearchStrategy(ColumnControlLogic::LessOrEqual),
            new ComparisonSearchStrategy(ColumnControlLogic::NotContains),
            new NullnessSearchStrategy(true),
            new ComparisonSearchStrategy(ColumnControlLogic::NotEqual),
            new ComparisonSearchStrategy(ColumnControlLogic::Starts),
        ]);
    }
}
