<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * Where the browser gets a table's rows from, resolved once from the table's configuration before
 * rendering or an Ajax request prepares it.
 *
 * @internal
 */
enum DataSourceMode
{
    /** Rows set through `data()` or `rows()`, embedded in the payload. */
    case Inline;

    /** An endpoint set through `ajax()`; an API Platform opt-in reads it with the Hydra adapter. */
    case Ajax;

    /** The entity's API Platform collection. */
    case ApiPlatform;

    /** The bundle's Ajax endpoint, paging on the server. */
    case ServerSide;

    /** Rows the table reads once at render time and embeds in the payload. */
    case ClientHydrated;

    /**
     * Opted into API Platform with no collection to read. Rendering throws, and the data and export
     * routes answer 404: falling back to Doctrine would skip the collection operation's authorization.
     */
    case ApiPlatformUnavailable;

    /**
     * @param class-string|null $entityClass
     *
     * @throws \LogicException when the table combines data() with an API Platform opt-in
     */
    public static function resolve(DataTable $table, ?string $entityClass, bool $apiPlatform, ?ApiResourceCollectionUrlResolver $collectionUrlResolver): self
    {
        if (null !== $table->getOption('ajax')) {
            return self::Ajax;
        }

        if (null !== $table->getOption('data')) {
            if ($apiPlatform) {
                throw new \LogicException(\sprintf('Table "%s" combines data() with an API Platform opt-in, which read rows from two places. Drop one of them.', $table->getDataTableClass() ?? $table->getId()));
            }

            return self::Inline;
        }

        if ($apiPlatform) {
            return null !== $entityClass && null !== $collectionUrlResolver?->resolveCollectionUrl($entityClass)
                ? self::ApiPlatform
                : self::ApiPlatformUnavailable;
        }

        return $table->isServerSide() ? self::ServerSide : self::ClientHydrated;
    }
}
