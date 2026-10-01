<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * Where the browser gets a table's rows from.
 *
 * Resolved from the table's current options, so rendering moves a table along: once prepared, an
 * ApiPlatform or ServerSide table holds the endpoint it was given and resolves as Ajax, and a
 * hydrated ClientHydrated table resolves as Inline.
 *
 * @internal
 */
enum DataSourceMode
{
    /** Rows set through `data()`, embedded in the payload. */
    case Inline;

    /** An endpoint already set through `ajax()`. */
    case Ajax;

    /** The entity's API Platform collection. */
    case ApiPlatform;

    /** The bundle's Ajax endpoint, paging on the server. */
    case ServerSide;

    /** Rows the table reads once at render time and embeds in the payload. */
    case ClientHydrated;

    public static function resolve(DataTable $table, ?AsDataTable $asDataTable, ?ApiResourceCollectionUrlResolver $collectionUrlResolver): self
    {
        if (null !== $table->getOption('ajax')) {
            return self::Ajax;
        }

        if (null !== $table->getOption('data')) {
            return self::Inline;
        }

        if (self::readsApiPlatformCollection($table, $asDataTable, $collectionUrlResolver)) {
            return self::ApiPlatform;
        }

        return $table->isServerSide() ? self::ServerSide : self::ClientHydrated;
    }

    /**
     * The `apiPlatform` option counts on its own: the fluent `apiPlatform()` sets it without the
     * attribute. A table opted in for an entity with no collection operation, or without API
     * Platform installed, reads its rows the way it would without the opt-in.
     */
    private static function readsApiPlatformCollection(DataTable $table, ?AsDataTable $asDataTable, ?ApiResourceCollectionUrlResolver $collectionUrlResolver): bool
    {
        if (null === $asDataTable || null === $collectionUrlResolver) {
            return false;
        }

        if (!$asDataTable->apiPlatform && true !== $table->getOption('apiPlatform')) {
            return false;
        }

        return null !== $collectionUrlResolver->resolveCollectionUrl($asDataTable->entityClass);
    }
}
