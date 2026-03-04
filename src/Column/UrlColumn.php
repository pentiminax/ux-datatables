<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class UrlColumn extends AbstractColumn
{
    public const string OPTION_TARGET             = 'target';
    public const string OPTION_DISPLAY_VALUE      = 'displayValue';
    public const string OPTION_ROUTE_NAME         = 'routeName';
    public const string OPTION_ROUTE_PARAMS       = 'routeParams';
    public const string OPTION_URL_TEMPLATE       = 'template';
    public const string OPTION_SHOW_EXTERNAL_ICON = 'showExternalIcon';

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML);
    }

    public function openInNewTab(): static
    {
        return $this->setTarget('_blank');
    }

    public function setTarget(string $target): static
    {
        $this->setCustomOption(self::OPTION_TARGET, $target);

        return $this;
    }

    public function setDisplayValue(string $displayValue): static
    {
        $this->setCustomOption(self::OPTION_DISPLAY_VALUE, $displayValue);

        return $this;
    }

    /**
     * @param array<string, string> $params Maps route parameter names to entity field names (e.g. ['id' => 'id'])
     */
    public function route(string $routeName, array $params = []): static
    {
        $this->setCustomOption(self::OPTION_ROUTE_NAME, $routeName);
        $this->setCustomOption(self::OPTION_ROUTE_PARAMS, $params);

        return $this;
    }

    public function showExternalIcon(bool $show = true): static
    {
        $this->setCustomOption(self::OPTION_SHOW_EXTERNAL_ICON, $show);

        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->getCustomOption(self::OPTION_ROUTE_NAME);
    }

    /**
     * @return array<string, string>|null
     */
    public function getRouteParams(): ?array
    {
        return $this->getCustomOption(self::OPTION_ROUTE_PARAMS);
    }

    public function setUrlTemplate(string $template): static
    {
        $this->setCustomOption(self::OPTION_URL_TEMPLATE, $template);

        return $this;
    }
}
