<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\ActionType;

final class Action implements \JsonSerializable
{
    private ActionType $type;
    private string $label;
    private string $className;
    private ?string $icon                    = null;
    private ?string $confirmationButtonLabel = null;
    private ?array $displayCondition         = null;
    private ?string $entityClass             = null;
    private array $htmlAttributes            = [];
    private string $idField                  = 'id';
    private ?string $url                     = null;
    private ?\Closure $urlResolver           = null;

    private function __construct(ActionType $type, string $label, string $className)
    {
        $this->type      = $type;
        $this->label     = $label;
        $this->className = $className;
    }

    public static function delete(string $label = 'Delete', string $className = 'btn btn-danger'): self
    {
        return new self(ActionType::Delete, $label, $className);
    }

    public static function detail(string $label = 'Detail', string $className = 'btn btn-primary'): self
    {
        return new self(ActionType::Detail, $label, $className);
    }

    public function getType(): ActionType
    {
        return $this->type;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function setClassName(string $className): self
    {
        $this->className = $className;

        return $this;
    }

    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function askConfirmation(string $buttonLabel): self
    {
        $this->confirmationButtonLabel = $buttonLabel;

        return $this;
    }

    public function displayIf(string $field, mixed $value): self
    {
        $this->displayCondition = ['field' => $field, 'value' => $value];

        return $this;
    }

    public function setEntityClass(string $entityClass): self
    {
        $this->entityClass = ltrim($entityClass, '\\');

        return $this;
    }

    /**
     * @param array<string, scalar|null> $htmlAttributes
     */
    public function setHtmlAttributes(array $htmlAttributes): self
    {
        $this->htmlAttributes = $htmlAttributes;

        return $this;
    }

    public function setIdField(string $idField): self
    {
        $this->idField = $idField;

        return $this;
    }

    public function linkToUrl(string|callable $url): self
    {
        if (\is_string($url)) {
            $this->url         = $url;
            $this->urlResolver = null;

            return $this;
        }

        $this->url         = null;
        $this->urlResolver = $url instanceof \Closure ? $url : $url(...);

        return $this;
    }

    public function resolveUrl(mixed $row): ?string
    {
        $url = $this->url;

        if (null !== $this->urlResolver) {
            $url = ($this->urlResolver)($row);
        }

        if (null === $url) {
            return null;
        }

        $url = trim($url);

        return '' === $url ? null : $url;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'type'      => $this->type->value,
            'label'     => $this->label,
            'className' => $this->className,
            'idField'   => $this->idField,
        ];

        if (null !== $this->icon) {
            $data['icon'] = $this->icon;
        }

        if (null !== $this->confirmationButtonLabel) {
            $data['confirm'] = $this->confirmationButtonLabel;
        }

        if (null !== $this->displayCondition) {
            $data['displayCondition'] = $this->displayCondition;
        }

        if (null !== $this->entityClass) {
            $data['entityClass'] = $this->entityClass;
        }

        $data['htmlAttributes'] = $this->htmlAttributes;

        if (null !== $this->url) {
            $data['url'] = $this->url;
        }

        return $data;
    }
}
