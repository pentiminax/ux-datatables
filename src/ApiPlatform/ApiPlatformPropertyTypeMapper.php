<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Symfony\Component\TypeInfo\Type;

final class ApiPlatformPropertyTypeMapper
{
    /**
     * Map a PropertyInfo type to the appropriate column class.
     *
     * Anything that is not a `Type` -- including `null`, when PropertyInfo could not resolve the
     * property -- falls back to a text column, which renders as well as anything else.
     *
     * @return class-string<ColumnInterface>
     */
    public function mapType(mixed $type): string
    {
        if (!$type instanceof Type) {
            return TextColumn::class;
        }

        $typeString = (string) $type;

        return match (true) {
            $this->isBoolean($typeString) => BooleanColumn::class,
            $this->isNumeric($typeString) => NumberColumn::class,
            $this->isDate($typeString)    => DateColumn::class,
            default                       => TextColumn::class,
        };
    }

    /**
     * Create a column instance from a property name, label, and type.
     */
    public function createColumn(string $name, string $label, mixed $type): ColumnInterface
    {
        $columnClass = $this->mapType($type);

        return $columnClass::new($name, $label);
    }

    private function isBoolean(string $typeString): bool
    {
        return str_contains($typeString, 'bool');
    }

    private function isNumeric(string $typeString): bool
    {
        return str_contains($typeString, 'int') || str_contains($typeString, 'float');
    }

    private function isDate(string $typeString): bool
    {
        return str_contains($typeString, 'DateTimeInterface') || str_contains($typeString, 'DateTime');
    }
}
