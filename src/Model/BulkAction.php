<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Contracts\ExecutableActionInterface;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Mutation\BulkActionContext;
use Pentiminax\UX\DataTables\Mutation\BulkRecords;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * An action executed over the rows the user selected, declared through
 * {@see AbstractDataTable::configureBulkActions()}.
 *
 * The handler runs on the bundle's bulk endpoint, after the table has been resolved from its
 * signed action token, the CSRF token validated, the static permission checked, and every
 * selected entity re-authorized one by one. It receives the authorized entities only.
 */
final class BulkAction implements \JsonSerializable, ExecutableActionInterface
{
    public const int DEFAULT_CHUNK_SIZE = 250;

    private ?string $icon                        = null;
    private ?string $lucideIcon                  = null;
    private ?string $confirmationMessage         = null;
    private ?string $confirmationButtonLabel     = null;
    private ?string $successMessage              = null;
    private ?\Closure $handler                   = null;
    private string|Expression|null $permission   = null;
    private ?\Closure $permissionSubjectResolver = null;
    private int $chunkSize                       = self::DEFAULT_CHUNK_SIZE;
    private bool $deselectRecordsAfterCompletion = true;
    private bool $denied                         = false;

    private function __construct(
        private readonly string $name,
        private string $label,
        private string $className,
    ) {
    }

    public static function new(string $name, string $label = '', string $className = ''): self
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Bulk action name must not be empty.');
        }

        return new self($name, '' !== $label ? $label : $name, $className);
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

    /**
     * Ask the user to confirm before the batch runs.
     *
     * `{count}` in the message is replaced client-side with the number of selected rows.
     */
    public function askConfirmation(string $message, ?string $buttonLabel = null): self
    {
        $this->confirmationMessage     = $message;
        $this->confirmationButtonLabel = $buttonLabel;

        return $this;
    }

    public function successMessage(?string $message): self
    {
        $this->successMessage = $message;

        return $this;
    }

    /**
     * The required closure that performs the batch.
     *
     * Signature: `function (BulkRecords $records, BulkActionContext $context): void`. It is called
     * once and consumes a lazy, single-pass iterable; see {@see BulkRecords}.
     *
     * @param callable(BulkRecords, BulkActionContext):void $handler
     */
    public function handler(callable $handler): self
    {
        $this->handler = $handler instanceof \Closure ? $handler : $handler(...);

        return $this;
    }

    public function getHandler(): ?\Closure
    {
        return $this->handler;
    }

    /**
     * Number of entities loaded, authorized and flushed per pass.
     */
    public function chunk(int $size): self
    {
        if ($size < 1) {
            throw new \InvalidArgumentException(\sprintf('Bulk action chunk size must be at least 1, %d given.', $size));
        }

        $this->chunkSize = $size;

        return $this;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Clear the selection once the batch has completed (default: enabled).
     */
    public function deselectRecordsAfterCompletion(bool $deselect = true): self
    {
        $this->deselectRecordsAfterCompletion = $deselect;

        return $this;
    }

    public function shouldDeselectRecordsAfterCompletion(): bool
    {
        return $this->deselectRecordsAfterCompletion;
    }

    /**
     * Restrict this action with a Symfony security attribute or expression.
     *
     * Without a subject resolver, the attribute is evaluated once before the batch starts. With a
     * resolver, it is evaluated again for every selected entity, and the resolver always receives
     * the entity itself — never a raw array row, unlike {@see Action::setPermission()}.
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

    public function jsonSerialize(): array
    {
        $data = [
            'name'      => $this->name,
            'label'     => $this->label,
            'className' => $this->className,
        ];

        if (null !== $this->icon) {
            $data['icon'] = $this->icon;
        }

        if (null !== $this->lucideIcon) {
            $data['lucideIcon'] = $this->lucideIcon;
        }

        if (null !== $this->confirmationMessage) {
            $data['confirm'] = $this->confirmationMessage;
        }

        if (null !== $this->confirmationButtonLabel) {
            $data['confirmButton'] = $this->confirmationButtonLabel;
        }

        if (null !== $this->successMessage) {
            $data['successMessage'] = $this->successMessage;
        }

        $data['deselectAfterCompletion'] = $this->deselectRecordsAfterCompletion;

        if ($this->denied) {
            $data['denied'] = true;

            unset($data['confirm'], $data['confirmButton'], $data['successMessage'], $data['deselectAfterCompletion']);
        }

        return $data;
    }
}
