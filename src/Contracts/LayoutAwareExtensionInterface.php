<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * Marker interface for extensions that are injected into the DataTables
 * `layout` configuration rather than serialized as top-level options.
 *
 * These extensions are skipped by DataTableExtensions::jsonSerialize() and
 * consumed by layout-building code.
 */
interface LayoutAwareExtensionInterface extends ExtensionInterface
{
}
