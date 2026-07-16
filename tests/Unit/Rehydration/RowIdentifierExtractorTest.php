<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Rehydration;

use Pentiminax\UX\DataTables\Rehydration\RowIdentifierExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RowIdentifierExtractor::class)]
final class RowIdentifierExtractorTest extends TestCase
{
    #[Test]
    public function it_extracts_an_integer_id(): void
    {
        $this->assertSame(7, (new RowIdentifierExtractor())->extract(['id' => 7]));
    }

    #[Test]
    public function it_extracts_a_string_id(): void
    {
        $this->assertSame('abc', (new RowIdentifierExtractor())->extract(['id' => 'abc']));
    }

    #[Test]
    public function it_extracts_the_last_segment_of_an_iri(): void
    {
        $this->assertSame('7', (new RowIdentifierExtractor())->extract(['@id' => '/api/users/7']));
    }

    #[Test]
    public function it_returns_null_when_no_identifier_is_present(): void
    {
        $this->assertNull((new RowIdentifierExtractor())->extract(['email' => 'user@example.com']));
    }

    #[Test]
    public function it_returns_null_when_the_iri_is_blank(): void
    {
        $this->assertNull((new RowIdentifierExtractor())->extract(['@id' => '   ']));
    }
}
