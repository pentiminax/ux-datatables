<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

final class StateExtension extends AbstractExtension
{
    // -1 → sessionStorage (session browser); 0 → localStorage sans expiration; >0 → localStorage, durée en secondes
    public function __construct(
        private int $duration = 7200,
    ) {
    }

    public function getKey(): string
    {
        return 'state';
    }

    public function jsonSerialize(): array
    {
        return ['duration' => $this->duration];
    }

    public function duration(int $seconds): static
    {
        if ($seconds < -1) {
            throw new \InvalidArgumentException('State duration must be -1 or greater.');
        }

        $this->duration = $seconds;

        return $this;
    }

    public function sessionStorage(): static
    {
        $this->duration = -1;

        return $this;
    }

    public function indefinite(): static
    {
        $this->duration = 0;

        return $this;
    }
}
