<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class UrlColumn extends AbstractColumn
{
    public const string OPTION_TARGET             = 'urlTarget';
    public const string OPTION_DISPLAY_VALUE      = 'urlDisplayValue';
    public const string OPTION_ROUTE_NAME         = 'urlRouteName';
    public const string OPTION_ROUTE_PARAMS       = 'urlRouteParams';
    public const string OPTION_URL_TEMPLATE       = 'urlTemplate';
    public const string OPTION_SHOW_EXTERNAL_ICON = 'urlShowExternalIcon';

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

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            array_filter([
                self::OPTION_TARGET             => $this->getCustomOption(self::OPTION_TARGET),
                self::OPTION_DISPLAY_VALUE      => $this->getCustomOption(self::OPTION_DISPLAY_VALUE),
                self::OPTION_ROUTE_PARAMS       => $this->getCustomOption(self::OPTION_ROUTE_PARAMS),
                self::OPTION_URL_TEMPLATE       => $this->getCustomOption(self::OPTION_URL_TEMPLATE),
                self::OPTION_SHOW_EXTERNAL_ICON => $this->getCustomOption(self::OPTION_SHOW_EXTERNAL_ICON),
            ], static fn (mixed $value) => null !== $value && '' !== $value)
        );
    }
}
