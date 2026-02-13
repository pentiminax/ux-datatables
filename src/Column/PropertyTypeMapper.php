<?php

namespace Pentiminax\UX\DataTables\Column;

final class PropertyTypeMapper
{
    /**
     * Map a PHP reflection type to the appropriate column class.
     *
     * @return class-string<AbstractColumn>
     */
    public function mapType(?\ReflectionNamedType $type): string
    {
        if (null === $type) {
            return TextColumn::class;
        }

        $typeName = $type->getName();

        return match (true) {
            'bool' === $typeName                                                    => BooleanColumn::class,
            \in_array($typeName, ['int', 'float'], true)                            => NumberColumn::class,
            !$type->isBuiltin() && is_a($typeName, \DateTimeInterface::class, true) => DateColumn::class,
            default                                                                 => TextColumn::class,
        };
    }
}
