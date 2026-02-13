<?php

namespace Pentiminax\UX\DataTables\Tests\Fixtures\ApiPlatform;

use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\TypeInfo\Type;

final class TestPropertyInfoExtractor implements PropertyInfoExtractorInterface
{
    /**
     * @var \Closure(string, string, array): ?Type
     */
    private \Closure $typeResolver;

    public function __construct()
    {
        $this->typeResolver = static fn (): ?Type => null;
    }

    /**
     * @param \Closure(string, string, array): ?Type $typeResolver
     */
    public function setTypeResolver(\Closure $typeResolver): void
    {
        $this->typeResolver = $typeResolver;
    }

    public function getType(string $class, string $property, array $context = []): ?Type
    {
        return ($this->typeResolver)($class, $property, $context);
    }

    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        $type = $this->getType($class, $property, $context);

        return null === $type ? null : [$type];
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        return null;
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        return null;
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        return null;
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        return null;
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        return null;
    }
}
