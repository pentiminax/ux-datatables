<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\EmailColumn;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(EmailColumn::class)]
final class EmailColumnTest extends DataTableTestCase
{
    #[Test]
    public function it_creates_html_type_column_with_only_the_email_marker(): void
    {
        $column = EmailColumn::new('email', 'Email Address');

        $this->assertColumnHeader($column, 'html', 'email', 'Email Address');
        $this->assertCustomOptions(['isEmail' => true], $column);
    }

    #[Test]
    #[DataProvider('provideCustomOptions')]
    public function it_serializes_custom_options(string $method, mixed $argument, string $option, mixed $expected): void
    {
        $column = EmailColumn::new('email')->{$method}($argument);

        $this->assertCustomOption($expected, $option, $column);
    }

    /**
     * @return iterable<string, array{string, mixed, string, mixed}>
     */
    public static function provideCustomOptions(): iterable
    {
        yield 'obfuscate enabled' => ['obfuscate', true, 'obfuscate', true];
        yield 'obfuscate disabled' => ['obfuscate', false, 'obfuscate', false];
        yield 'mask enabled' => ['mask', true, 'mask', true];
        yield 'mask disabled' => ['mask', false, 'mask', false];
        yield 'render as text enabled' => ['renderAsText', true, 'renderAsText', true];
        yield 'render as text disabled' => ['renderAsText', false, 'renderAsText', false];
        yield 'display value' => ['setDisplayValue', 'Contact us', 'displayValue', 'Contact us'];
    }

    #[Test]
    public function it_serializes_full_configuration(): void
    {
        $column = EmailColumn::new('email', 'Email Address')
            ->obfuscate()
            ->mask()
            ->setDisplayValue('Contact')
            ->renderAsText();

        $this->assertCustomOptions([
            'isEmail'      => true,
            'obfuscate'    => true,
            'mask'         => true,
            'displayValue' => 'Contact',
            'renderAsText' => true,
        ], $column);
    }
}
