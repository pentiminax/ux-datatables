<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

final class MercureConfig implements \JsonSerializable
{
    public function __construct(
        public readonly string $hubUrl,
        public readonly string $topic,
        public readonly bool $withCredentials = false,
        public readonly ?int $debounceMs = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        $data = [
            'hubUrl' => $this->hubUrl,
            'topic'  => $this->topic,
        ];

        if ($this->withCredentials) {
            $data['withCredentials'] = $this->withCredentials;
        }

        if (null !== $this->debounceMs) {
            $data['debounceMs'] = $this->debounceMs;
        }

        return $data;
    }
}
