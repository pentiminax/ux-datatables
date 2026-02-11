<?php

namespace Pentiminax\UX\DataTables\Query\Strategy;

class DefaultSearchStrategyRegistry extends SearchStrategyRegistry
{
    public function __construct()
    {
        parent::__construct([
            new EqualSearchStrategy(),
            new NotEqualSearchStrategy(),
            new ContainsSearchStrategy(),
            new NotContainsSearchStrategy(),
            new StartsWithSearchStrategy(),
            new EndsWithSearchStrategy(),
            new GreaterThanSearchStrategy(),
            new GreaterOrEqualSearchStrategy(),
            new LessThanSearchStrategy(),
            new LessOrEqualSearchStrategy(),
            new EmptySearchStrategy(),
            new NotEmptySearchStrategy(),
            new InListSearchStrategy(),
        ]);
    }
}