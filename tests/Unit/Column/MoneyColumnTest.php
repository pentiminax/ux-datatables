<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(MoneyColumn::class)]
final class MoneyColumnTest extends DataTableTestCase
{
    #[Test]
    public function it_creates_num_type_column_with_default_money_options(): void
    {
        $column = MoneyColumn::new('price', 'Price');

        $this->assertColumnHeader($column, 'num', 'price', 'Price');
        $this->assertCustomOptions([
            'isMoney'       => true,
            'currency'      => 'EUR',
            'storedAsCents' => true,
            'decimals'      => 2,
        ], $column);
    }

    #[Test]
    public function it_does_not_set_show_currency_sign_by_default(): void
    {
        $this->assertNoCustomOption('showCurrencySign', MoneyColumn::new('price'));
    }

    #[Test]
    #[DataProvider('provideCustomOptions')]
    public function it_serializes_custom_options(string $method, mixed $argument, string $option, mixed $expected): void
    {
        $column = MoneyColumn::new('price')->{$method}($argument);

        $this->assertCustomOption($expected, $option, $column);
    }

    /**
     * @return iterable<string, array{string, mixed, string, mixed}>
     */
    public static function provideCustomOptions(): iterable
    {
        yield 'currency is uppercased' => ['currency', 'usd', 'currency', 'USD'];
        yield 'currency via setter' => ['setCurrency', 'GBP', 'currency', 'GBP'];
        yield 'stored as units' => ['storedAsCents', false, 'storedAsCents', false];
        yield 'decimals' => ['decimals', 0, 'decimals', 0];
        yield 'currency sign shown' => ['showCurrencySign', true, 'showCurrencySign', true];
        yield 'currency sign hidden' => ['showCurrencySign', false, 'showCurrencySign', false];
    }

    #[Test]
    #[DataProvider('provideInvalidConfigurations')]
    public function it_rejects_invalid_configuration(string $method, mixed $argument, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        MoneyColumn::new('price')->{$method}($argument);
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'unknown currency' => ['currency', 'euro', 'The currency "EURO" is not a valid ISO 4217 currency code.'];
        yield 'decimals below minimum' => ['decimals', -1, 'The number of decimals must be between 0 and 20.'];
        yield 'decimals above maximum' => ['decimals', 21, 'The number of decimals must be between 0 and 20.'];
    }
}
