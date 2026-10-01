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

    /**
     * Opted into API Platform with no collection to read. No rows are read: falling back to
     * Doctrine would skip the authorization the collection operation applies.
     */
    case ApiPlatformUnavailable;

    public static function resolve(DataTable $table, ?AsDataTable $asDataTable, ?ApiResourceCollectionUrlResolver $collectionUrlResolver): self
    {
        if (null !== $table->getOption('ajax')) {
            return self::Ajax;
        }

        if (null !== $table->getOption('data')) {
            return self::Inline;
        }

        if (true === $asDataTable?->apiPlatform || true === $table->getOption('apiPlatform')) {
            return self::resolvesCollection($asDataTable, $collectionUrlResolver) ? self::ApiPlatform : self::ApiPlatformUnavailable;
        }

        return $table->isServerSide() ? self::ServerSide : self::ClientHydrated;
    }

    /**
     * The `apiPlatform` option counts on its own: the fluent `apiPlatform()` sets it without the
     * attribute. The collection URL needs the attribute's entity and API Platform installed.
     */
    private static function resolvesCollection(?AsDataTable $asDataTable, ?ApiResourceCollectionUrlResolver $collectionUrlResolver): bool
    {
        if (null === $asDataTable || null === $collectionUrlResolver) {
            return false;
        }

        return null !== $collectionUrlResolver->resolveCollectionUrl($asDataTable->entityClass);
    }
}
