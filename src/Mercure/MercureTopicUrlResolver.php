<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * Builds the absolute URL of a route path the way UrlGeneratorInterface::ABSOLUTE_URL does.
 */
final class MercureTopicUrlResolver
{
    public function __construct(
        private readonly ?RouterInterface $router = null,
    ) {
    }

    public function absoluteUrl(string $path): string
    {
        $context = $this->router?->getContext();

        if (null === $context) {
            return $path;
        }

        return $this->buildSchemeAuthority($context).$context->getBaseUrl().$path;
    }

    private function buildSchemeAuthority(RequestContext $context): string
    {
        $scheme = $context->getScheme();
        $host   = $context->getHost();

        if ('' === $host && ('' === $scheme || 'http' === $scheme || 'https' === $scheme)) {
            return '';
        }

        return ('' === $scheme ? '//' : $scheme.'://').$host.$this->buildPort($scheme, $context);
    }

    private function buildPort(string $scheme, RequestContext $context): string
    {
        if ('http' === $scheme && 80 !== $context->getHttpPort()) {
            return ':'.$context->getHttpPort();
        }

        if ('https' === $scheme && 443 !== $context->getHttpsPort()) {
            return ':'.$context->getHttpsPort();
        }

        return '';
    }
}
