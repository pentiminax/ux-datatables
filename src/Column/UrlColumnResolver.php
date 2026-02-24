<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Symfony\Component\Routing\RouterInterface;

class UrlColumnResolver
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @param ColumnInterface[] $columns
     */
    public function resolveRoutes(array $columns): void
    {
        foreach ($columns as $column) {
            if (!$column instanceof UrlColumn || null === $column->getRouteName()) {
                continue;
            }

            $route = $this->router->getRouteCollection()->get($column->getRouteName());

            if (null === $route) {
                throw new \InvalidArgumentException(\sprintf('Route "%s" does not exist.', $column->getRouteName()));
            }

            $urlTemplate = $this->router->getContext()->getBaseUrl().$route->getPath();

            $column->setUrlTemplate($urlTemplate);
        }
    }
}
