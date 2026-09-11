<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Enum\Icon;
use Symfony\Component\ExpressionLanguage\Expression;

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
    private string|Expression|null $permission   = null;
    private ?\Closure $permissionSubjectResolver = null;
    private ?string $collapsibleTemplate         = null;
    private array $collapsibleParameters         = [];
    private ?ActionsPosition $position           = null;
    private bool $disabledWhenDenied             = false;
    private bool $denied                         = false;

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
     * Restrict this action with a Symfony security attribute or expression.
     *
     * Without a subject resolver, the attribute is evaluated once before serialization
     * (e.g. `ROLE_ADMIN`). With a resolver, the attribute is evaluated per row, but the value the
     * resolver receives differs by call site:
     *
     * - At render time, it receives the row source passed to the rendering pipeline
     *   ({@see \Pentiminax\UX\DataTables\RowMapper\RowContext::$source}), which is the entity under
     *   a projected (Doctrine) provider, but may be a plain array for a QueryBuilder or provider
     *   that hydrates array rows instead of entities.
     * - On the mutation endpoints (`delete`, `edit-form`, `detail`), it always receives the
     *   entity located by id, never a raw array row.
     *
     * A resolver used on both paths should tolerate either shape (entity or array), or the action
     * should use a static permission (no resolver) instead.
     */
    public function setPermission(string|Expression $attribute, ?callable $subjectResolver = null): self
    {
        $this->permission                = $attribute;
        $this->permissionSubjectResolver = null === $subjectResolver
            ? null
            : ($subjectResolver instanceof \Closure ? $subjectResolver : $subjectResolver(...));

        return $this;
    }

    public function getPermission(): string|Expression|null
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

    /**
     * Render the action as a disabled control instead of hiding it when its permission is denied.
     *
     * The disabled control never carries a URL, a CSRF token, or a row id, and the mutation
     * endpoints still enforce the permission on their own.
     */
    public function disabledWhenDenied(bool $disabled = true): self
    {
        $this->disabledWhenDenied = $disabled;

        return $this;
    }

    public function isDisabledWhenDenied(): bool
    {
        return $this->disabledWhenDenied;
    }

    /**
     * @internal copy marked as denied by a static permission check, serialized without actionable data
     */
    public function asDenied(): self
    {
        $clone         = clone $this;
        $clone->denied = true;

        return $clone;
    }

    public function isDenied(): bool
    {
        return $this->denied;
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

        if ($this->disabledWhenDenied) {
            $data['disabledWhenDenied'] = true;
        }

        if ($this->denied) {
            $data['denied'] = true;

            unset($data['url'], $data['ajaxMethod'], $data['entityClass'], $data['confirm'], $data['collapsible']);
        }

        return $data;
    }
}
