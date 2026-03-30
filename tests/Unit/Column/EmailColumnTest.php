<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\EmailColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EmailColumn::class)]
final class EmailColumnTest extends TestCase
{
    #[Test]
    public function it_creates_html_type_column(): void
    {
        $data = EmailColumn::new('email', 'Email Address')->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertSame('email', $data['data']);
        $this->assertSame('email', $data['name']);
        $this->assertSame('Email Address', $data['title']);
    }

    #[Test]
    public function it_falls_back_to_name_as_title(): void
    {
        $data = EmailColumn::new('email')->jsonSerialize();

        $this->assertSame('email', $data['title']);
    }

    #[Test]
    public function it_has_no_custom_options_by_default(): void
    {
        $data = EmailColumn::new('email')->jsonSerialize();

        $this->assertArrayNotHasKey('customOptions', $data);
    }

    #[Test]
    public function it_stores_obfuscate_option(): void
    {
        $data = EmailColumn::new('email')
            ->obfuscate()
            ->jsonSerialize();

        $this->assertTrue($data['customOptions'][EmailColumn::OPTION_OBFUSCATE]);
    }

    #[Test]
    public function it_can_disable_obfuscation(): void
    {
        $data = EmailColumn::new('email')
            ->obfuscate(false)
            ->jsonSerialize();

        $this->assertFalse($data['customOptions'][EmailColumn::OPTION_OBFUSCATE]);
    }

    #[Test]
    public function it_returns_obfuscated_flag(): void
    {
        $column = EmailColumn::new('email')->obfuscate();

        $this->assertTrue($column->isObfuscated());
    }

    #[Test]
    public function it_returns_false_for_obfuscated_by_default(): void
    {
        $column = EmailColumn::new('email');

        $this->assertFalse($column->isObfuscated());
    }

    #[Test]
    public function it_stores_display_value(): void
    {
        $data = EmailColumn::new('email')
            ->setDisplayValue('Contact us')
            ->jsonSerialize();

        $this->assertSame('Contact us', $data['customOptions'][EmailColumn::OPTION_DISPLAY_VALUE]);
    }

    #[Test]
    public function it_returns_null_display_value_by_default(): void
    {
        $column = EmailColumn::new('email');

        $this->assertNull($column->getDisplayValue());
    }

    #[Test]
    public function it_returns_stored_display_value(): void
    {
        $column = EmailColumn::new('email')->setDisplayValue('Contact us');

        $this->assertSame('Contact us', $column->getDisplayValue());
    }

    #[Test]
    public function it_serializes_full_configuration(): void
    {
        $data = EmailColumn::new('email', 'Email Address')
            ->obfuscate()
            ->setDisplayValue('Contact us')
            ->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertSame('email', $data['name']);
        $this->assertSame('Email Address', $data['title']);
        $this->assertTrue($data['customOptions'][EmailColumn::OPTION_OBFUSCATE]);
        $this->assertSame('Contact us', $data['customOptions'][EmailColumn::OPTION_DISPLAY_VALUE]);
    }
}
