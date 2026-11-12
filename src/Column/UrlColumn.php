<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UrlColumn extends AbstractColumn
{
    public const OPTION_IS_URL                 = 'isUrl';
    public const OPTION_TARGET                 = 'target';
    public const OPTION_DISPLAY_VALUE          = 'displayValue';
    public const OPTION_SHOW_EXTERNAL_ICON     = 'showExternalIcon';
    public const OPTION_DEFAULT_PROTOCOL       = 'defaultProtocol';
    public const OPTION_ALLOWED_PROTOCOLS      = 'allowedProtocols';
    public const OPTION_RENDER_EMPTY_AS_ANCHOR = 'renderEmptyAsAnchor';
    public const OPTION_HAS_URL_RESOLVER       = 'hasUrlResolver';

    private const UNSAFE_PROTOCOLS = ['javascript', 'data', 'vbscript', 'file'];

    private ?string $url               = null;
    private ?\Closure $urlResolver     = null;
    private ?string $routeName         = null;
    private ?\Closure $routeParameters = null;

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML)
            ->customOption(self::OPTION_IS_URL, true);
    }

    public function openInNewTab(): static
    {
        $this->customOption(self::OPTION_TARGET, '_blank');

        return $this;
    }

    public function displayValue(string $displayValue): static
    {
        $this->customOption(self::OPTION_DISPLAY_VALUE, $displayValue);

        return $this;
    }

    /**
     * @deprecated since 1.6, use displayValue() instead. Removed in 2.0.
     */
    public function setDisplayValue(string $displayValue): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "displayValue()" instead.', __METHOD__);

        return $this->displayValue($displayValue);
    }

    public function defaultProtocol(string $protocol): static
    {
        $this->customOption(self::OPTION_DEFAULT_PROTOCOL, $this->normalizeProtocol($protocol));

        return $this;
    }

    /**
     * @deprecated since 1.6, use defaultProtocol() instead. Removed in 2.0.
     */
    public function setDefaultProtocol(string $protocol): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "defaultProtocol()" instead.', __METHOD__);

        return $this->defaultProtocol($protocol);
    }

    /**
     * @param list<string> $protocols
     */
    public function allowedProtocols(array $protocols): static
    {
        $normalized = [];

        foreach ($protocols as $protocol) {
            $normalized[] = $this->normalizeProtocol($protocol);
        }

        $this->customOption(self::OPTION_ALLOWED_PROTOCOLS, array_values(array_unique($normalized)));

        return $this;
    }

    private function normalizeProtocol(string $protocol): string
    {
        $normalized = strtolower(rtrim(trim($protocol), ':'));

        if ('' === $normalized) {
            throw new \InvalidArgumentException('Protocol cannot be empty.');
        }

        if (1 !== preg_match('/^[a-z][a-z0-9+.-]*$/', $normalized)) {
            throw new \InvalidArgumentException(\sprintf('Invalid protocol format: "%s".', $protocol));
        }

        if (\in_array($normalized, self::UNSAFE_PROTOCOLS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsafe protocol "%s" is not allowed.', $normalized));
        }

        return $normalized;
    }

    public function linkToRoute(string $routeName, array|callable|null $params = null): static
    {
        $this->routeName = $routeName;

        if (null === $params) {
            $this->routeParameters = null;
        } elseif (\is_array($params)) {
            $this->routeParameters = static fn (): array => $params;
        } else {
            $this->routeParameters = $params(...);
        }

        $this->url         = null;
        $this->urlResolver = null;

        $this->customOption(self::OPTION_HAS_URL_RESOLVER, true);

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

        $this->customOption(self::OPTION_HAS_URL_RESOLVER, true);

        return $this;
    }

    public function showExternalIcon(bool $show = true): static
    {
        $this->customOption(self::OPTION_SHOW_EXTERNAL_ICON, $show);

        return $this;
    }

    /**
     * By default, a row with no resolvable URL renders as plain (escaped) text instead of an
     * `<a>` tag, since a href-less anchor has no accessible name/purpose.
     * `renderEmptyAsAnchor(true)` renders the `<a>` tag anyway, for stylesheets or scripts that
     * expect one in every cell.
     */
    public function renderEmptyAsAnchor(bool $render = true): static
    {
        $this->customOption(self::OPTION_RENDER_EMPTY_AS_ANCHOR, $render);

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
