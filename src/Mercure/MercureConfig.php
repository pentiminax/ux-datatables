<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

final class MercureConfig implements \JsonSerializable
{
    /**
     * @var string[]
     */
    public readonly array $topics;

    public function __construct(
        public readonly string $hubUrl,
        array $topics,
        public readonly bool $withCredentials = false,
        public readonly ?int $debounceMs = null,
    ) {
        $this->topics = array_values(array_filter(
            $topics,
            static fn (mixed $value): bool => \is_string($value) && '' !== $value
        ));

        if ([] === $this->topics) {
            throw new \InvalidArgumentException('Mercure topics cannot be empty.');
        }
    }

    public function jsonSerialize(): array
    {
        $data = [
            'hubUrl' => $this->hubUrl,
            'topics' => $this->topics,
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
