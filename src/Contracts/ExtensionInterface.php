<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * A DataTables.net extension's server-side configuration, serialized into the table's options and
 * lazy-loaded on the client by the Stimulus controller.
 *
 * getKey() is the extension's stable identifier (`buttons`, `select`, ...) and must be unique
 * within one table: DataTableExtensions indexes on it. jsonSerialize() must return only what the
 * client understands for that key, and enabled(false) must keep the extension out of the payload
 * rather than shipping an inert entry.
 *
 * Extend {@see \Pentiminax\UX\DataTables\Model\Extensions\AbstractExtension} for the
 * enabled-flag plumbing. Tables declare extensions in
 * AbstractDataTable::configureExtensions(); there is no service tag.
 */
interface ExtensionInterface extends \JsonSerializable
{
    public function getKey(): string;

    public function enabled(bool $enabled = true): static;

    public function isEnabled(): bool;
}
