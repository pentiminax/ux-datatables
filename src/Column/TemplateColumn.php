<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class TemplateColumn extends AbstractColumn
{
    public const string OPTION_TEMPLATE_PATH       = 'templatePath';
    public const string OPTION_TEMPLATE_PARAMETERS = 'templateParameters';

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML);
    }

    public function setTemplate(string $template, array $parameters = []): static
    {
        $template = trim($template);
        if ('' === $template) {
            throw new \InvalidArgumentException('Template path cannot be empty.');
        }

        $this->setCustomOption(self::OPTION_TEMPLATE_PATH, $template);
        $this->setCustomOption(self::OPTION_TEMPLATE_PARAMETERS, $parameters);

        return $this;
    }

    public function getTemplate(): string
    {
        $template = $this->getCustomOption(self::OPTION_TEMPLATE_PATH);
        if (!\is_string($template) || '' === trim($template)) {
            throw new \LogicException(\sprintf('Template path is not configured for column "%s".', $this->getName()));
        }

        return $template;
    }

    public function getTemplateParameters(): array
    {
        return $this->getCustomOption(self::OPTION_TEMPLATE_PARAMETERS) ?? [];
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            [self::OPTION_TEMPLATE_PATH => $this->getTemplate()]
        );
    }
}
