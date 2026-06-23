<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Implemented by filters exposing labels that must be translated at render time.
 *
 * Labels are resolved lazily so the translator stays out of user-facing
 * configuration code (configureFilters()); translation is applied by the
 * RenderingPreparer, mirroring how column titles are handled.
 */
interface TranslatableFilterInterface
{
    public function translateLabels(TranslatorInterface $translator, ?string $locale = null): void;
}
