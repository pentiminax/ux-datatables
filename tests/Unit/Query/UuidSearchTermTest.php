<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Pentiminax\UX\DataTables\Query\UuidSearchTerm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UuidSearchTerm::class)]
final class UuidSearchTermTest extends TestCase
{
    private const string UUID = '018f2c3e-1234-7abc-9def-0123456789ab';

    private const string ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function normalized_terms(): iterable
    {
        yield 'uuid without a field type' => [self::UUID, null, self::UUID];
        yield 'ulid without a field type' => [self::ULID, null, self::ULID];
        yield 'padded uuid' => ['  '.self::UUID.'  ', 'guid', self::UUID];
        yield 'uuid on guid' => [self::UUID, 'guid', self::UUID];
        yield 'uuid on uuid' => [self::UUID, 'uuid', self::UUID];
        yield 'uuid on uuid_binary_ordered_time' => [self::UUID, 'uuid_binary_ordered_time', self::UUID];
        yield 'ulid on uuid_binary_ordered_time' => [self::ULID, 'uuid_binary_ordered_time', null];
        yield 'ulid on ulid' => [self::ULID, 'ulid', self::ULID];
        yield 'ulid on guid' => [self::ULID, 'guid', null];
        yield 'ulid on uuid' => [self::ULID, 'uuid', null];
        yield 'ulid on uuid_binary' => [self::ULID, 'uuid_binary', null];
        yield 'uuid on ulid' => [self::UUID, 'ulid', null];
        yield 'partial identifier' => ['018f2c3e', 'guid', null];
        yield 'unhyphenated uuid' => ['018f2c3e12347abc9def0123456789ab', 'uuid', null];
        yield 'empty string' => ['', 'ulid', null];
    }

    #[Test]
    #[DataProvider('normalized_terms')]
    public function it_normalizes_an_identifier_for_the_resolved_field_type(
        string $value,
        ?string $doctrineType,
        ?string $expected,
    ): void {
        $this->assertSame($expected, UuidSearchTerm::normalize($value, $doctrineType));
    }
}
