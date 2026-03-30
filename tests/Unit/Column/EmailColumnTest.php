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
    public function it_always_sets_is_email_marker(): void
    {
        $data = EmailColumn::new('email')->jsonSerialize();

        $this->assertTrue($data['customOptions']['isEmail']);
    }

    #[Test]
    public function it_has_only_is_email_marker_by_default(): void
    {
        $data = EmailColumn::new('email')->jsonSerialize();

        $this->assertSame(['isEmail' => true], $data['customOptions']);
    }

    #[Test]
    public function it_enables_obfuscation(): void
    {
        $data = EmailColumn::new('email')->obfuscate()->jsonSerialize();

        $this->assertTrue($data['customOptions']['obfuscate']);
    }

    #[Test]
    public function it_disables_obfuscation(): void
    {
        $data = EmailColumn::new('email')->obfuscate(false)->jsonSerialize();

        $this->assertFalse($data['customOptions']['obfuscate']);
    }

    #[Test]
    public function it_enables_masking(): void
    {
        $data = EmailColumn::new('email')->mask()->jsonSerialize();

        $this->assertTrue($data['customOptions']['mask']);
    }

    #[Test]
    public function it_disables_masking(): void
    {
        $data = EmailColumn::new('email')->mask(false)->jsonSerialize();

        $this->assertFalse($data['customOptions']['mask']);
    }

    #[Test]
    public function it_stores_display_value(): void
    {
        $data = EmailColumn::new('email')->setDisplayValue('Contact us')->jsonSerialize();

        $this->assertSame('Contact us', $data['customOptions']['displayValue']);
    }

    #[Test]
    public function it_serializes_full_configuration(): void
    {
        $data = EmailColumn::new('email', 'Email Address')
            ->obfuscate()
            ->mask()
            ->setDisplayValue('Contact')
            ->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertTrue($data['customOptions']['isEmail']);
        $this->assertTrue($data['customOptions']['obfuscate']);
        $this->assertTrue($data['customOptions']['mask']);
        $this->assertSame('Contact', $data['customOptions']['displayValue']);
    }
}
