<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Export;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;

/**
 * The body of an export {@see \Symfony\Component\HttpFoundation\StreamedResponse}, as a named
 * invokable rather than a closure so it can be tested on its own.
 *
 * Nothing is caught here: {@see ExportService} resolves every failure it can before the response is
 * returned, while headers can still become an error response. What is left -- a mapper or a query
 * failing mid-iteration -- propagates to the application's own error handling instead of being
 * swallowed into a silently truncated download.
 */
final readonly class ExportStream
{
    /**
     * @param list<ColumnInterface>          $columns
     * @param iterable<array<string, mixed>> $rows
     */
    public function __construct(
        private ExporterInterface $exporter,
        private array $columns,
        private iterable $rows,
    ) {
    }

    public function __invoke(): void
    {
        $this->exporter->write($this->columns, $this->rows);
    }
}
