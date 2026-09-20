<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

final class MercureConfig implements \JsonSerializable
{
    /**
     * The legacy dialect: a `topic` query parameter and URI Template selectors.
     */
    public const PROTOCOL_VERSION_0_X = '0.x';

    /**
     * The Mercure 1.0 dialect: `match`/`match_urlpattern` query parameters and URL Patterns.
     */
    public const PROTOCOL_VERSION_1_0 = '1.0';

    /**
     * @var string[]
     */
    public readonly array $topics;

    public readonly ?string $hubUrl;

    public readonly string $protocolVersion;

    public function __construct(
        array $topics,
        public readonly bool $withCredentials = false,
        public readonly ?int $debounceMs = null,
        ?string $hubUrl = null,
        string $protocolVersion = self::PROTOCOL_VERSION_0_X,
    ) {
        $this->topics = array_values(array_filter(
            $topics,
            static fn (mixed $value): bool => \is_string($value) && '' !== $value
        ));

        if ([] === $this->topics) {
            throw new \InvalidArgumentException('Mercure topics cannot be empty.');
        }

        $this->hubUrl = $hubUrl;

        // An unreported version is not a newer one: defaulting to the legacy dialect keeps the
        // subscription parameters identical to what a hub that never opted into 1.0 always got.
        $this->protocolVersion = '' === $protocolVersion ? self::PROTOCOL_VERSION_0_X : $protocolVersion;
    }

    public function withHubUrl(string $hubUrl): self
    {
        return new self(
            topics: $this->topics,
            withCredentials: $this->withCredentials,
            debounceMs: $this->debounceMs,
            hubUrl: $hubUrl,
            protocolVersion: $this->protocolVersion,
        );
    }

    public function withProtocolVersion(string $protocolVersion): self
    {
        return new self(
            topics: $this->topics,
            withCredentials: $this->withCredentials,
            debounceMs: $this->debounceMs,
            hubUrl: $this->hubUrl,
            protocolVersion: $protocolVersion,
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

        // The legacy dialect is the client's default, so a 0.x hub keeps serializing the
        // exact payload it always did: the key only shows up when it changes the dialect.
        if (self::PROTOCOL_VERSION_0_X !== $this->protocolVersion) {
            $data['protocolVersion'] = $this->protocolVersion;
        }

        if ($this->withCredentials) {
            $data['withCredentials'] = $this->withCredentials;
        }

        if (null !== $this->debounceMs) {
            $data['debounceMs'] = $this->debounceMs;
        }

        return $data;
    }
}
