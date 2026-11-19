<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

/**
 * Applies an option map to a built object through the bare fluent method carrying each option
 * (`field()`). The alias table holds only what does not follow that convention.
 */
final class OptionApplier
{
    /**
     * @param array<string, \Closure(object, mixed): void> $aliases
     */
    public function __construct(private readonly array $aliases = [])
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function apply(object $target, array $options): void
    {
        foreach ($options as $option => $value) {
            $alias = $this->aliases[$option] ?? null;

            if (null !== $alias) {
                $alias($target, $value);

                continue;
            }

            if (!method_exists($target, $option)) {
                throw new \InvalidArgumentException(\sprintf('Option "%s" is not supported by "%s".', $option, $target::class));
            }

            $target->{$option}($value);
        }
    }
}
