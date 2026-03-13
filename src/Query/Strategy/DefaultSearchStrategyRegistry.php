<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

class DefaultSearchStrategyRegistry extends SearchStrategyRegistry
{
    public function __construct()
    {
        parent::__construct([
            new ComparisonSearchStrategy('equal', '=', '%s'),
            new ComparisonSearchStrategy('notEqual', '!=', '%s'),
            new ContainsSearchStrategy(),
            new ComparisonSearchStrategy('notContains', 'NOT LIKE', '%%%s%%'),
            new ComparisonSearchStrategy('starts', 'LIKE', '%s%%'),
            new ComparisonSearchStrategy('ends', 'LIKE', '%%%s'),
            new ComparisonSearchStrategy('greater', '>', '%s'),
            new ComparisonSearchStrategy('greaterOrEqual', '>=', '%s'),
            new ComparisonSearchStrategy('less', '<', '%s'),
            new ComparisonSearchStrategy('lessOrEqual', '<=', '%s'),
            new EmptySearchStrategy(),
            new NotEmptySearchStrategy(),
            new InListSearchStrategy(),
        ]);
    }
}
