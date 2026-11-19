<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Symfony\Component\Mercure\HubInterface;

class MercureHubUrlResolver
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    public function resolveHubUrl(): ?string
    {
        $hubUrl = $this->hub->getPublicUrl();

        return '' === $hubUrl ? null : $hubUrl;
    }

    /**
     * The Mercure protocol version spoken by the configured hub.
     *
     * The browser needs it to pick its subscription dialect: 0.x hubs take `topic=` with URI
     * Template selectors, 1.0 hubs take `match=`/`match_urlpattern=` with URL Patterns.
     */
    public function resolveProtocolVersion(): string
    {
        return $this->hub->getProtocolVersion()->value;
    }
}
