<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Enum\Icon;

final class Action implements \JsonSerializable
{
    private ActionType $type;
    private string $name;
    private string $label;
    private string $className;
    private ?string $icon                        = null;
    private ?string $lucideIcon                  = null;
    private ?string $confirmationButtonLabel     = null;
    private ?array $displayCondition             = null;
    private ?string $entityClass                 = null;
    private array $htmlAttributes                = [];
    private string $idField                      = 'id';
    private ?string $url                         = null;
    private ?\Closure $urlResolver               = null;
    private ?string $routeName                   = null;
    private array $routeParameters               = [];
    private ?\Closure $routeParametersResolver   = null;
    private ?string $ajaxMethod                  = null;
    private ?string $csrfTokenId                 = null;
    private ?\Closure $csrfTokenIdResolver       = null;
    private ?string $permission                  = null;
    private ?\Closure $permissionSubjectResolver = null;
    private ?string $collapsibleTemplate         = null;
    private array $collapsibleParameters         = [];
    private ?ActionsPosition $position           = null;

    private function __construct(ActionType $type, string $name, string $label, string $className)
    {
        $this->type      = $type;
        $this->name      = $name;
        $this->label     = $label;
        $this->className = $className;
    }

    public static function delete(string $label = 'Delete', string $className = 'btn btn-danger'): self
    {
        return new self(ActionType::Delete, ActionType::Delete->value, $label, $className);
    }

    public static function detail(string $label = 'Detail', string $className = 'btn btn-primary'): self
    {
        return new self(ActionType::Detail, ActionType::Detail->value, $label, $className);
    }

    public static function edit(string $label = 'Edit', string $className = 'btn btn-warning'): self
    {
        return new self(ActionType::Edit, ActionType::Edit->value, $label, $className);
    }

    public static function new(string $name, string $label = '', string $className = ''): self
    {
        return new self(ActionType::Custom, $name, $label, $className);
    }

    public function getType(): ActionType
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function setClassName(string $className): self
    {
        $this->className = $className;

        return $this;
    }

    public function icon(string|Icon $icon): self
    {
        if ($icon instanceof Icon) {
            $this->icon       = null;
            $this->lucideIcon = $icon->value;

            return $this;
        }

        $this->icon       = $icon;
        $this->lucideIcon = null;

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

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    /**
     * @param array<string, scalar|null> $htmlAttributes
     */
    public function htmlAttributes(array $htmlAttributes): self
    {
        $this->htmlAttributes = $htmlAttributes;

        return $this;
    }

    public function setIdField(string $idField): self
    {
        $this->idField = $idField;

        return $this;
    }

    public function getIdField(): string
    {
        return $this->idField;
    }

    /**
     * Override the column placement for this action only.
     *
     * When set, this action is rendered in a dedicated actions column placed
     * before or after the data columns, independently of the collection-level
     * position. When null (default), the action inherits the {@see Actions}
     * collection position.
     */
    public function position(?ActionsPosition $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getPosition(): ?ActionsPosition
    {
        return $this->position;
    }

    /**
     * Render this action as an arrow control that expands the row into a child row,
     * lazily fetching the given Twig template (which receives the current row as `entity`).
     *
     * @param array<string, mixed> $parameters extra context merged into the template
     */
    public function collapsible(string $template, array $parameters = []): self
    {
        $this->collapsibleTemplate   = $template;
        $this->collapsibleParameters = $parameters;

        return $this;
    }

    public function getCollapsibleTemplate(): ?string
    {
        return $this->collapsibleTemplate;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCollapsibleParameters(): array
    {
        return $this->collapsibleParameters;
    }

    public function isCollapsible(): bool
    {
        return null !== $this->collapsibleTemplate;
    }

    public function linkToUrl(string|callable $url): self
    {
        $this->routeName               = null;
        $this->routeParameters         = [];
        $this->routeParametersResolver = null;

        if (\is_string($url)) {
            $this->url         = $url;
            $this->urlResolver = null;

            return $this;
        }

        $this->url         = null;
        $this->urlResolver = $url instanceof \Closure ? $url : $url(...);

        return $this;
    }

    /**
     * Target a Symfony route, generated per row when the action is resolved.
     *
     * Mutually exclusive with {@see self::linkToUrl()}: the last call wins.
     *
     * @param array<string, mixed>|callable|null $params static route parameters, or a callable
     *                                                   receiving the raw row and returning them
     */
    public function linkToRoute(string $routeName, array|callable|null $params = null): self
    {
        $this->url         = null;
        $this->urlResolver = null;
        $this->routeName   = $routeName;

        if (null === $params || \is_array($params)) {
            $this->routeParameters         = $params ?? [];
            $this->routeParametersResolver = null;

            return $this;
        }

        $this->routeParameters         = [];
        $this->routeParametersResolver = $params instanceof \Closure ? $params : $params(...);

        return $this;
    }

    /**
     * Send this action as a same-origin Ajax request instead of navigating to its URL.
     *
     * The CSRF token id is resolved per row and its value is sent as `_token`. The application
     * endpoint remains responsible for validating it (`#[IsCsrfTokenValid]`) and for
     * authorization (`#[IsGranted]`).
     *
     * @param string|callable $csrfTokenId a token id, or a callable receiving the raw row
     * @param string          $method      `POST` or `DELETE`
     */
    public function asAjaxRequest(string|callable $csrfTokenId, string $method = 'POST'): self
    {
        $normalizedMethod = strtoupper($method);

        if (!\in_array($normalizedMethod, ['POST', 'DELETE'], true)) {
            throw new \InvalidArgumentException(\sprintf('Ajax action method must be "POST" or "DELETE", "%s" given.', $method));
        }

        $this->ajaxMethod = $normalizedMethod;

        if (\is_string($csrfTokenId)) {
            $this->csrfTokenId         = $csrfTokenId;
            $this->csrfTokenIdResolver = null;

            return $this;
        }

        $this->csrfTokenId         = null;
        $this->csrfTokenIdResolver = $csrfTokenId instanceof \Closure ? $csrfTokenId : $csrfTokenId(...);

        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function isAjaxRequest(): bool
    {
        return null !== $this->ajaxMethod;
    }

    public function getAjaxMethod(): ?string
    {
        return $this->ajaxMethod;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveRouteParameters(mixed $row): array
    {
        if (null === $this->routeParametersResolver) {
            return $this->routeParameters;
        }

        return ($this->routeParametersResolver)($row);
    }

    public function resolveCsrfTokenId(mixed $row): ?string
    {
        $tokenId = null !== $this->csrfTokenIdResolver
            ? ($this->csrfTokenIdResolver)($row)
            : $this->csrfTokenId;

        if (null === $tokenId) {
            return null;
        }

        $tokenId = trim((string) $tokenId);

        return '' === $tokenId ? null : $tokenId;
    }

    /**
     * Restrict this action with a Symfony security attribute (role, voter, expression).
     *
     * Without a subject resolver, the attribute is evaluated once before serialization
     * (e.g. `ROLE_ADMIN`). With a resolver, the attribute is evaluated per row and
     * the resolver receives the raw row passed to the rendering pipeline.
     */
    public function permission(string $attribute, ?callable $subjectResolver = null): self
    {
        $this->permission                = $attribute;
        $this->permissionSubjectResolver = null === $subjectResolver
            ? null
            : ($subjectResolver instanceof \Closure ? $subjectResolver : $subjectResolver(...));

        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function getPermissionSubjectResolver(): ?\Closure
    {
        return $this->permissionSubjectResolver;
    }

    public function hasStaticPermission(): bool
    {
        return null !== $this->permission && null === $this->permissionSubjectResolver;
    }

    public function hasPerRowPermission(): bool
    {
        return null !== $this->permission && null !== $this->permissionSubjectResolver;
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
            'name'      => $this->name,
            'label'     => $this->label,
            'className' => $this->className,
            'idField'   => $this->idField,
        ];

        if (null !== $this->icon) {
            $data['icon'] = $this->icon;
        }

        if (null !== $this->lucideIcon) {
            $data['lucideIcon'] = $this->lucideIcon;
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

        if (null !== $this->ajaxMethod) {
            $data['ajaxMethod'] = $this->ajaxMethod;
        }

        if ($this->isCollapsible()) {
            $data['collapsible'] = true;
        }

        return $data;
    }
}
