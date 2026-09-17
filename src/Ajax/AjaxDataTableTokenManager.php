<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

final class AjaxDataTableTokenManager
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $secret,
    ) {
        if ('' === $this->secret) {
            throw new \InvalidArgumentException('A non-empty secret is required to generate DataTable Ajax tokens.');
        }
    }

    public function generateHmacSignature(string $dataTableClass): string
    {
        $signature = base64_encode(hash_hmac('sha256', $dataTableClass, $this->secret));

        return strtr($signature, [
            '+' => '',
            '=' => '',
        ]);
    }
}
