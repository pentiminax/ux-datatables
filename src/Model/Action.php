<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\ActionType;

final class Action implements \JsonSerializable
{
    private ActionType $type;
    private string $label;
    private string $cssClass;
    private ?string $icon;
    private ?string $confirmationButtonLabel;
    private ?array $displayCondition;
    private ?string $entityClass;
    private string $idField;
    private ?string $url;
    private ?\Closure $urlResolver;

    private function __construct(ActionType $type, string $label, string $cssClass)
    {
        $this->type                    = $type;
        $this->label                   = $label;
        $this->cssClass                = $cssClass;
        $this->icon                    = null;
        $this->confirmationButtonLabel = null;
        $this->displayCondition        = null;
        $this->entityClass             = null;
        $this->idField                 = 'id';
        $this->url                     = null;
        $this->urlResolver             = null;
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

    public function setCssClass(string $cssClass): self
    {
        $this->cssClass = $cssClass;

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
        $this->urlResolver = $url instanceof \Closure ? $url : \Closure::fromCallable($url);

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

        $url = trim((string) $url);

        return '' === $url ? null : $url;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'type'     => $this->type->value,
            'label'    => $this->label,
            'cssClass' => $this->cssClass,
            'idField'  => $this->idField,
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

        if (null !== $this->url) {
            $data['url'] = $this->url;
        }

        return $data;
    }
}
