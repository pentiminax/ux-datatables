<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

/**
 * An identifier whose PHP value differs from the string the browser sends and from what the
 * column stores, like a Symfony Uuid does.
 */
final readonly class PrefixedId implements \Stringable
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
