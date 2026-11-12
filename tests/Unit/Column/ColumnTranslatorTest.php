<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\ColumnTranslator;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Expression;
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
        $choice = ChoiceColumn::new('status', 'status')->choices(ColumnTranslatorStatus::class);

        ColumnTranslator::translate([$text, $choice], $translator);

        $this->assertSame('NAME', $text->getTitle());
        $this->assertSame('STATUS', $choice->getTitle());
        $this->assertSame(['open' => 'OPEN'], $choice->getCustomOption(ChoiceColumn::OPTION_CHOICES));
    }

    #[Test]
    public function it_translates_the_title_of_a_column_implementing_the_interface_with_set_title(): void
    {
        $column = new ColumnTranslatorSettableTitleColumn('name');

        ColumnTranslator::translate([$column], $this->upperCaseTranslator());

        $this->assertSame('NAME', $column->getTitle());
    }

    #[Test]
    public function it_skips_the_title_of_a_column_implementing_the_interface_without_set_title(): void
    {
        $column = new ColumnTranslatorInterfaceColumn('name');

        ColumnTranslator::translate([$column], $this->upperCaseTranslator());

        $this->assertSame('name', $column->getTitle());
    }

    private function upperCaseTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return strtoupper($id);
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
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

class ColumnTranslatorInterfaceColumn implements ColumnInterface
{
    public function __construct(
        protected string $title,
    ) {
    }

    public function getName(): string
    {
        return $this->title;
    }

    public function getField(): ?string
    {
        return null;
    }

    public function setField(string $field): static
    {
        return $this;
    }

    public function getOrderExpression(): ?string
    {
        return null;
    }

    public function setVisible(bool $visible): static
    {
        return $this;
    }

    public function isSearchable(): bool
    {
        return false;
    }

    public function isGlobalSearchable(): bool
    {
        return false;
    }

    public function getData(): ?string
    {
        return null;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function isNumber(): bool
    {
        return false;
    }

    public function isDate(): bool
    {
        return false;
    }

    public function getType(): ColumnType
    {
        return ColumnType::STRING;
    }

    public function isVisible(): bool
    {
        return true;
    }

    public function isOrderable(): bool
    {
        return false;
    }

    public function isExportable(): bool
    {
        return false;
    }

    public function getClassName(): ?string
    {
        return null;
    }

    public function getCustomOption(string $optionName): mixed
    {
        return null;
    }

    public function getCustomOptions(): array
    {
        return [];
    }

    public function getPermission(): string|Expression|null
    {
        return null;
    }

    public function jsonSerialize(): array
    {
        return [];
    }
}

final class ColumnTranslatorSettableTitleColumn extends ColumnTranslatorInterfaceColumn
{
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }
}
