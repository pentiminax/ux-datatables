<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * A DataTables.net extension's server-side configuration, serialized into the table's options and
 * lazy-loaded on the client by the Stimulus controller.
 *
 * getKey() is the extension's stable identifier (`buttons`, `select`, ...) and must be unique
 * within one table: DataTableExtensions indexes on it. jsonSerialize() must return only what the
 * client understands for that key. An extension is active as soon as it is declared.
 *
 * Tables declare extensions through DataTable's fluent methods; there is no service tag.
 */
interface ExtensionInterface extends \JsonSerializable
{
    public function getKey(): string;
}
