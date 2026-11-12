<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Contracts\TemplateAwareColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;

class TemplateColumn extends AbstractColumn implements TemplateAwareColumnInterface
{
    public const OPTION_TEMPLATE_PATH       = 'templatePath';
    public const OPTION_TEMPLATE_PARAMETERS = 'templateParameters';

    private array $templateParameters = [];

    protected function isSummarizable(): bool
    {
        return false;
    }

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML)
            ->orderable(false)
            ->searchable(false)
            ->exportable(false)
            ->disableGlobalSearch();
    }

    /**
     * @param array<string, mixed> $parameters Extra Twig variables. Keys `row`, `source`, `payload`,
     *                                         `data`, `column`, and `entity` are reserved and throw
     *                                         when passed.
     */
    public function template(string $template, array $parameters = []): static
    {
        $template = trim($template);
        if ('' === $template) {
            throw new \InvalidArgumentException('Template path cannot be empty.');
        }

        $reserved = array_intersect_key($parameters, array_flip(TemplateColumnRenderer::RESERVED_CONTEXT_KEYS));
        if ([] !== $reserved) {
            throw new \InvalidArgumentException(\sprintf('Template parameters "%s" are reserved by the renderer and cannot be overridden on column "%s".', implode('", "', array_keys($reserved)), $this->getName()));
        }

        $this->customOption(self::OPTION_TEMPLATE_PATH, $template);
        $this->templateParameters = $parameters;

        return $this;
    }

    /**
     * @deprecated since 1.6, use template() instead. Removed in 2.0.
     */
    public function setTemplate(string $template, array $parameters = []): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "template()" instead.', __METHOD__);

        return $this->template($template, $parameters);
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
        return $this->templateParameters;
    }
}
