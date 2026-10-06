<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Count;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Stores PrefixedId('a') as "db-a". Anything but lowercase letters and digits is refused.
 */
final class PrefixedIdType extends Type
{
    public const string NAME = 'dt_prefixed_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PrefixedId
    {
        return null === $value ? null : new PrefixedId(substr((string) $value, 3));
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        $string = (string) $value;
        if (1 !== preg_match('/^[a-z0-9]+$/', $string)) {
            throw new ConversionException(\sprintf('"%s" is not a prefixed identifier.', $string));
        }

        return 'db-'.$string;
    }
}
