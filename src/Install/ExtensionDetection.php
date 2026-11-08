<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Install;

/**
 * @internal
 */
final class ExtensionDetection
{
    /**
     * @param list<string>          $extensions selectable names, in catalog order
     * @param array<string, string> $failures   error message per table service id
     */
    public function __construct(
        public readonly array $extensions,
        public readonly array $failures,
    ) {
    }
}
