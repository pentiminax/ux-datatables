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
     *
     * Falls back to the legacy version when the installed symfony/mercure predates
     * HubInterface::getProtocolVersion() (0.8), so appliances that never opted into 1.0 keep
     * sending the parameters they always did.
     */
    public function resolveProtocolVersion(): string
    {
        if (!method_exists($this->hub, 'getProtocolVersion')) {
            return MercureConfig::PROTOCOL_VERSION_0_X;
        }

        $protocolVersion = $this->hub->getProtocolVersion();

        return match (true) {
            $protocolVersion instanceof \BackedEnum => (string) $protocolVersion->value,
            \is_string($protocolVersion)            => $protocolVersion,
            default                                 => MercureConfig::PROTOCOL_VERSION_0_X,
        };
    }
}
