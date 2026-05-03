<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

final class MercureConfig implements \JsonSerializable
{
    /**
     * @var string[]
     */
    public readonly array $topics;

    public readonly ?string $hubUrl;

    public function __construct(
        array $topics,
        public readonly bool $withCredentials = false,
        public readonly ?int $debounceMs = null,
        ?string $hubUrl = null,
    ) {
        $this->topics = array_values(array_filter(
            $topics,
            static fn (mixed $value): bool => \is_string($value) && '' !== $value
        ));

        if ([] === $this->topics) {
            throw new \InvalidArgumentException('Mercure topics cannot be empty.');
        }

        $this->hubUrl = $hubUrl;
    }

    public function withHubUrl(string $hubUrl): self
    {
        return new self(
            topics: $this->topics,
            withCredentials: $this->withCredentials,
            debounceMs: $this->debounceMs,
            hubUrl: $hubUrl,
        );
    }

    public function jsonSerialize(): array
    {
        if (null === $this->hubUrl || '' === $this->hubUrl) {
            throw new \LogicException('MercureConfig hubUrl is not set. It must be resolved before serialization.');
        }

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
