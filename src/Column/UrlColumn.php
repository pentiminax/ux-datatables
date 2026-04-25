<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UrlColumn extends AbstractColumn
{
    public const string OPTION_IS_URL             = 'isUrl';
    public const string OPTION_TARGET             = 'target';
    public const string OPTION_DISPLAY_VALUE      = 'displayValue';
    public const string OPTION_SHOW_EXTERNAL_ICON = 'showExternalIcon';

    private ?string $url               = null;
    private ?\Closure $urlResolver     = null;
    private ?string $routeName         = null;
    private ?\Closure $routeParameters = null;

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML)
            ->setCustomOption(self::OPTION_IS_URL, true);
    }

    public function openInNewTab(): static
    {
        $this->setCustomOption(self::OPTION_TARGET, '_blank');

        return $this;
    }

    public function setDisplayValue(string $displayValue): static
    {
        $this->setCustomOption(self::OPTION_DISPLAY_VALUE, $displayValue);

        return $this;
    }

    public function linkToRoute(string $routeName, ?callable $params = null): static
    {
        $this->routeName       = $routeName;
        $this->routeParameters = null === $params ? null : $params(...);
        $this->url             = null;
        $this->urlResolver     = null;

        return $this;
    }

    public function linkToUrl(string|callable $url): static
    {
        if (\is_string($url)) {
            $this->url         = $url;
            $this->urlResolver = null;
        } else {
            $this->url         = null;
            $this->urlResolver = $url(...);
        }

        $this->routeName       = null;
        $this->routeParameters = null;

        return $this;
    }

    public function showExternalIcon(bool $show = true): static
    {
        $this->setCustomOption(self::OPTION_SHOW_EXTERNAL_ICON, $show);

        return $this;
    }

    public function resolveUrl(mixed $row, ?UrlGeneratorInterface $urlGenerator = null): ?string
    {
        $url = $this->url;

        if (null !== $this->urlResolver) {
            $url = ($this->urlResolver)($row);
        }

        if (null !== $this->routeName) {
            if (null === $urlGenerator) {
                throw new \LogicException('UrlGeneratorInterface is required to resolve UrlColumn routes.');
            }

            $params = null === $this->routeParameters ? [] : ($this->routeParameters)($row);
            if (!\is_array($params)) {
                throw new \UnexpectedValueException(\sprintf('Route parameters for column "%s" must be an array.', $this->getName()));
            }

            $url = $urlGenerator->generate($this->routeName, $params);
        }

        if (null === $url) {
            return null;
        }

        $url = trim($url);

        return '' === $url ? null : $url;
    }

    public function hasUrlResolver(): bool
    {
        return null !== $this->url || null !== $this->urlResolver || null !== $this->routeName;
    }
}
