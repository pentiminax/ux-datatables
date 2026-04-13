<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * Marker interface for columns that resolve their value from a Symfony route.
 *
 * Implementations are picked up by the URL resolver at table-build time to
 * inject a fully-qualified URL template based on the configured route name.
 */
interface RouteAwareColumnInterface extends ColumnInterface
{
    /**
     * Returns the Symfony route name backing this column, or null when none is configured.
     */
    public function getRouteName(): ?string;

    /**
     * Stores the resolved URL template (e.g. "/users/{id}") on the column for client-side rendering.
     */
    public function setUrlTemplate(string $template): static;
}
