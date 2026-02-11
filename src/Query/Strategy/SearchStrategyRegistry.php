<?php

namespace Pentiminax\UX\DataTables\Query\Strategy;

/**
 * Registry for search strategies with fallback to default strategy.
 *
 * Maps logic identifier strings (e.g., 'equal', 'contains') to their
 * corresponding strategy implementations. Unknown logic types fall back
 * to the default strategy (ContainsSearchStrategy).
 */
class SearchStrategyRegistry
{
    /**
     * @var array<string, SearchStrategyInterface>
     */
    private array $strategies = [];

    private SearchStrategyInterface $defaultStrategy;

    public function __construct(array $strategies = [], ?SearchStrategyInterface $defaultStrategy = null)
    {
        $this->defaultStrategy = $defaultStrategy ?? new ContainsSearchStrategy();

        foreach ($strategies as $strategy) {
            $this->register($strategy);
        }
    }

    public function register(SearchStrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getLogic()] = $strategy;
    }

    public function get(string $logic): SearchStrategyInterface
    {
        return $this->strategies[$logic] ?? $this->defaultStrategy;
    }

    public function has(string $logic): bool
    {
        return isset($this->strategies[$logic]);
    }
}
