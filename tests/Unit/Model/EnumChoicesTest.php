<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Model\EnumChoices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(EnumChoices::class)]
final class EnumChoicesTest extends TestCase
{
    /**
     * @return iterable<string, array{array<mixed>, bool}>
     */
    public static function lists(): iterable
    {
        yield 'enum cases' => [EnumChoicesPlain::cases(), true];
        yield 'empty list' => [[], false];
        yield 'associative array' => [['a' => EnumChoicesPlain::A], false];
        yield 'mixed list' => [[EnumChoicesPlain::A, 'b'], false];
    }

    /**
     * @param array<mixed> $choices
     */
    #[Test]
    #[DataProvider('lists')]
    public function it_detects_a_list_of_backed_enum_cases(array $choices, bool $expected): void
    {
        $this->assertSame($expected, EnumChoices::isList($choices));
    }

    #[Test]
    public function it_labels_cases_from_get_label_then_label_then_the_case_name(): void
    {
        $this->assertSame(['a' => 'Label A', 'b' => 'B'], EnumChoices::labels(EnumChoicesPlain::cases()));
        $this->assertSame(['x' => 'Short X'], EnumChoices::labels(EnumChoicesShortLabel::cases()));
    }

    #[Test]
    public function it_keeps_only_translatable_cases(): void
    {
        $this->assertSame([], EnumChoices::translatableCases(EnumChoicesPlain::cases()));
        $this->assertSame(['t' => EnumChoicesTranslatable::T], EnumChoices::translatableCases(EnumChoicesTranslatable::cases()));
    }
}

enum EnumChoicesPlain: string
{
    case A = 'a';
    case B = 'b';

    public function getLabel(): string
    {
        return self::A === $this ? 'Label A' : $this->name;
    }
}

enum EnumChoicesShortLabel: string
{
    case X = 'x';

    public function label(): string
    {
        return 'Short X';
    }
}

enum EnumChoicesTranslatable: string implements TranslatableInterface
{
    case T = 't';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('t', [], null, $locale);
    }
}
