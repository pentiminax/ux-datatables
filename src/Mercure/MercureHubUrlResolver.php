<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Contracts\MercureHubUrlResolverInterface;
use Symfony\Component\Mercure\HubInterface;

final class MercureHubUrlResolver implements MercureHubUrlResolverInterface
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
}
