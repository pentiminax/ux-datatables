<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper;

use Pentiminax\UX\DataTables\Highlight\HighlightConfig;
use Pentiminax\UX\DataTables\RowMapper\RowIdField;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountCustomer;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountDocument;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountLine;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountTag;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RowIdField::class)]
final class RowIdFieldTest extends TestCase
{
    use BuildsEntityManager;

    /**
     * @param class-string|null $entityClass
     */
    #[Test]
    #[DataProvider('provideConfigurations')]
    public function it_names_the_field_written_as_dt_row_id(
        ?string $highlightField,
        ?string $bulkIdField,
        ?string $entityClass,
        ?string $expected,
    ): void {
        $metadata = null === $entityClass
            ? null
            : $this->createEntityManager(CountCustomer::class, CountTag::class, CountDocument::class, CountLine::class)->getClassMetadata($entityClass);

        $this->assertSame($expected, RowIdField::resolve(
            null === $highlightField ? null : new HighlightConfig(idField: $highlightField),
            $bulkIdField,
            static fn () => $metadata,
        ));
    }

    #[Test]
    #[DataProvider('provideConfigurationsWithoutRemapping')]
    public function it_reads_no_metadata_when_the_field_cannot_be_remapped(?string $highlightField, ?string $bulkIdField): void
    {
        RowIdField::resolve(
            null === $highlightField ? null : new HighlightConfig(idField: $highlightField),
            $bulkIdField,
            fn () => $this->fail('Metadata must not be read.'),
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function provideConfigurationsWithoutRemapping(): iterable
    {
        yield 'no row id' => [null, null];
        yield 'explicit bulk id field' => [null, 'name'];
        yield 'highlight field' => ['id', 'id'];
    }

    /**
     * @return iterable<string, array{?string, ?string, ?class-string, ?string}>
     */
    public static function provideConfigurations(): iterable
    {
        yield 'no highlight and no bulk actions' => [null, null, CountDocument::class, null];
        yield 'default bulk id remapped onto the primary key' => [null, 'id', CountDocument::class, 'pk'];
        yield 'default bulk id already the primary key' => [null, 'id', CountCustomer::class, 'id'];
        yield 'explicit bulk id field kept' => [null, 'name', CountDocument::class, 'name'];
        yield 'default bulk id kept without metadata' => [null, 'id', null, 'id'];
        yield 'default bulk id kept on a composite key' => [null, 'id', CountLine::class, 'id'];
        yield 'highlight field never remapped' => ['id', 'id', CountDocument::class, 'id'];
        yield 'highlight field wins over the bulk id field' => ['name', 'pk', CountDocument::class, 'name'];
    }
}
