<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\ColumnTranslator;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(ColumnTranslator::class)]
final class ColumnTranslatorTest extends TestCase
{
    #[Test]
    public function it_translates_titles_and_translatable_choices_in_place(): void
    {
        $translator = new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return strtoupper($id);
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };

        $text   = TextColumn::new('name', 'name');
        $choice = ChoiceColumn::new('status', 'status')->setChoices(ColumnTranslatorStatus::class);

        ColumnTranslator::translate([$text, $choice], $translator);

        $this->assertSame('NAME', $text->getTitle());
        $this->assertSame('STATUS', $choice->getTitle());
        $this->assertSame(['open' => 'OPEN'], $choice->getCustomOption(ChoiceColumn::OPTION_CHOICES));
    }
}

enum ColumnTranslatorStatus: string implements TranslatableInterface
{
    case Open = 'open';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->value, [], null, $locale);
    }
}
