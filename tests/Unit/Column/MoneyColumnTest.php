<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\MoneyColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MoneyColumn::class)]
final class MoneyColumnTest extends TestCase
{
    #[Test]
    public function it_creates_num_type_column(): void
    {
        $data = MoneyColumn::new('price', 'Price')->jsonSerialize();

        $this->assertSame('num', $data['type']);
        $this->assertSame('price', $data['data']);
        $this->assertSame('price', $data['name']);
        $this->assertSame('Price', $data['title']);
    }

    #[Test]
    public function it_falls_back_to_name_as_title(): void
    {
        $data = MoneyColumn::new('price')->jsonSerialize();

        $this->assertSame('price', $data['title']);
    }

    #[Test]
    public function it_sets_default_money_options(): void
    {
        $data = MoneyColumn::new('price')->jsonSerialize();

        $this->assertSame([
            'isMoney'       => true,
            'currency'      => 'EUR',
            'storedAsCents' => true,
            'decimals'      => 2,
        ], $data['customOptions']);
    }

    #[Test]
    public function it_normalizes_currency_to_uppercase(): void
    {
        $data = MoneyColumn::new('price')
            ->currency('usd')
            ->jsonSerialize();

        $this->assertSame('USD', $data['customOptions']['currency']);
    }

    #[Test]
    public function it_can_configure_currency_storage_and_decimals(): void
    {
        $data = MoneyColumn::new('price')
            ->setCurrency('GBP')
            ->storedAsCents(false)
            ->decimals(0)
            ->jsonSerialize();

        $this->assertSame('GBP', $data['customOptions']['currency']);
        $this->assertFalse($data['customOptions']['storedAsCents']);
        $this->assertSame(0, $data['customOptions']['decimals']);
    }

    #[Test]
    public function it_rejects_invalid_currency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The currency "EURO" is not a valid ISO 4217 currency code.');

        MoneyColumn::new('price')->currency('euro');
    }

    #[Test]
    public function it_rejects_decimals_below_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The number of decimals must be between 0 and 20.');

        MoneyColumn::new('price')->decimals(-1);
    }

    #[Test]
    public function it_rejects_decimals_above_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The number of decimals must be between 0 and 20.');

        MoneyColumn::new('price')->decimals(21);
    }

    #[Test]
    public function it_can_show_currency_sign(): void
    {
        $data = MoneyColumn::new('price')
            ->showCurrencySign()
            ->jsonSerialize();

        $this->assertTrue($data['customOptions']['showCurrencySign']);
    }

    #[Test]
    public function it_can_hide_currency_sign(): void
    {
        $data = MoneyColumn::new('price')
            ->showCurrencySign(false)
            ->jsonSerialize();

        $this->assertFalse($data['customOptions']['showCurrencySign']);
    }

    #[Test]
    public function it_does_not_set_show_currency_sign_by_default(): void
    {
        $data = MoneyColumn::new('price')->jsonSerialize();

        $this->assertArrayNotHasKey('showCurrencySign', $data['customOptions']);
    }
}
