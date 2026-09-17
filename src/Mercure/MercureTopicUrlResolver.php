<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * Turns a relative resource path into the absolute URL API Platform publishes.
 *
 * API Platform builds every resource IRI at UrlGeneratorInterface::ABS_URL, and that generator
 * derives the scheme, host, port and front controller base path from the routing request context.
 * Reusing the same context keeps the auto-resolved Mercure topic byte-identical with the published
 * IRI, so the hub's own host stops deciding whether a live update matches: the alternative is a
 * relative topic, which the hub resolves against its own URL and which therefore only ever works
 * while the hub shares the application's origin.
 *
 * Without a router at all the path is returned unchanged, keeping the previous relative behavior.
 * With a router, the context is the one the application configured for it — the current request's
 * during an HTTP request, the `router.request_context` parameters otherwise (which is the very
 * context API Platform's IRI generation falls back to in a console command or a worker) — so the
 * topic and the published IRI cannot drift.
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

    /**
     * Mirrors Symfony's UrlGenerator scheme authority for an absolute URL: the scheme, the host,
     * and the port whenever it is not the scheme's default one.
     *
     * MercureTopicUrlResolverTest compares this with a real UrlGenerator on the same context, so a
     * divergence from the generator API Platform delegates to fails the build.
     */
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
