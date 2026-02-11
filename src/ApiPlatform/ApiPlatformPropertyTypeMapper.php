<?php

namespace Pentiminax\UX\DataTables\ApiPlatform;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;

final class ApiPlatformPropertyTypeMapper
{
    /**
     * Map a PropertyInfo type to the appropriate column class.
     *
     * @return class-string<AbstractColumn>
     */
    public function mapType(mixed $type): string
    {
        if ($type instanceof Type) {
            return $this->mapTypeInfoType($type);
        }

        if ($type instanceof LegacyType) {
            return $this->mapLegacyType($type);
        }

        return TextColumn::class;
    }

    /**
     * Create a column instance from a property name, label, and type.
     */
    public function createColumn(string $name, string $label, mixed $type): AbstractColumn
    {
        $columnClass = $this->mapType($type);

        return $columnClass::new($name, $label);
    }

    /**
     * @return class-string<AbstractColumn>
     */
    private function mapTypeInfoType(Type $type): string
    {
        $typeString = (string) $type;

        if (str_contains($typeString, 'bool')) {
            return BooleanColumn::class;
        }

        if (str_contains($typeString, 'int') || str_contains($typeString, 'float')) {
            return NumberColumn::class;
        }

        if (str_contains($typeString, 'DateTimeInterface') || str_contains($typeString, 'DateTime')) {
            return DateColumn::class;
        }

        return TextColumn::class;
    }

    /**
     * @return class-string<AbstractColumn>
     */
    private function mapLegacyType(LegacyType $type): string
    {
        $builtinType = $type->getBuiltinType();

        if ('bool' === $builtinType) {
            return BooleanColumn::class;
        }

        if ('int' === $builtinType || 'float' === $builtinType) {
            return NumberColumn::class;
        }

        if ('object' === $builtinType) {
            $className = $type->getClassName();
            if (null !== $className && is_a($className, \DateTimeInterface::class, true)) {
                return DateColumn::class;
            }
        }

        return TextColumn::class;
    }
}
