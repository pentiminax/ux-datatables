<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

/**
 * Applies an option map to a built object through whichever fluent method carries it.
 *
 * Columns expose setters (`setField()`) while filters expose bare fluent methods (`field()`), so
 * both spellings are tried before the alias table, which holds only what follows neither.
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

            $method = $this->resolveMethod($target, $option);

            if (null === $method) {
                throw new \InvalidArgumentException(\sprintf('Option "%s" is not supported by "%s".', $option, $target::class));
            }

            $target->{$method}($value);
        }
    }

    /**
     * @param object|class-string $target
     */
    public function supports(object|string $target, string $option): bool
    {
        return isset($this->aliases[$option]) || null !== $this->resolveMethod($target, $option);
    }

    /**
     * @param object|class-string $target
     */
    private function resolveMethod(object|string $target, string $option): ?string
    {
        foreach (['set'.ucfirst($option), $option] as $method) {
            if (method_exists($target, $method)) {
                return $method;
            }
        }

        return null;
    }
}
