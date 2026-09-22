<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Test;

use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The payload a rendered DataTable carries in the page, decoded.
 *
 * DataTables.net does not run in a PHPUnit test, so client-side sorting, searching, and paging
 * are not exercised here: this asserts what left PHP, not what a browser would show.
 */
final class DataTableView
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        private readonly array $payload,
    ) {
    }

    /**
     * @param string|null $actionToken the action token of the table to read, when the page carries
     *                                 more than one
     */
    public static function fromCrawler(Crawler $crawler, ?string $actionToken = null): self
    {
        $payloads = [];

        foreach ($crawler->filter('['.DataTablesExtension::VIEW_ATTRIBUTE.']') as $node) {
            \assert($node instanceof \DOMElement);

            $raw     = $node->getAttribute(DataTablesExtension::VIEW_ATTRIBUTE);
            $decoded = json_decode(html_entity_decode($raw), true);

            if (!\is_array($decoded)) {
                throw new \LogicException(\sprintf('A DataTable view attribute could not be decoded as JSON: %s', substr($raw, 0, 200)));
            }

            $payloads[] = $decoded;
        }

        if (null !== $actionToken) {
            $payloads = array_values(array_filter(
                $payloads,
                static fn (array $payload): bool => ($payload['dataTable'] ?? null) === $actionToken,
            ));
        }

        if (1 !== \count($payloads)) {
            throw new \LogicException(\sprintf('Expected exactly one DataTable in the page, found %d. Pass the table class as the second argument to select one.', \count($payloads)));
        }

        return new self($payloads[0]);
    }

    /**
     * The rows embedded in the page. A server-side table embeds none: fetch those with
     * {@see DataTableTestCase::dataTable()} instead.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = $this->payload['data'] ?? [];

        return \is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function row(int $index): array
    {
        $rows = $this->rows();

        if (!isset($rows[$index])) {
            Assert::fail(\sprintf('Row %d does not exist, the rendered table carries %d row(s).', $index, \count($rows)));
        }

        return $rows[$index];
    }

    /**
     * @return list<string> the column names, in render order
     */
    public function columns(): array
    {
        $columns = $this->payload['columns'] ?? [];

        if (!\is_array($columns)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $column): string => (string) ($column['name'] ?? $column['data'] ?? ''),
            $columns,
        ));
    }

    public function option(string $name): mixed
    {
        return $this->payload[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->payload;
    }

    public function assertRowCount(int $expected): self
    {
        Assert::assertCount($expected, $this->rows(), 'Unexpected number of rows in the rendered DataTable.');

        return $this;
    }

    /**
     * @param list<string> $expected in render order
     */
    public function assertColumns(array $expected): self
    {
        Assert::assertSame($expected, $this->columns(), 'Unexpected columns in the rendered DataTable.');

        return $this;
    }

    /**
     * @param list<mixed> $expected in row order
     */
    public function assertColumnValues(string $column, array $expected): self
    {
        $rows = $this->rows();

        if ([] !== $rows && !\array_key_exists($column, $rows[0])) {
            Assert::fail(\sprintf('Column "%s" is absent from the rendered rows. Available columns: %s.', $column, implode(', ', array_keys($rows[0]))));
        }

        Assert::assertSame($expected, array_column($rows, $column), \sprintf('Unexpected values for column "%s".', $column));

        return $this;
    }

    public function assertOption(string $name, mixed $expected): self
    {
        Assert::assertSame($expected, $this->option($name), \sprintf('Unexpected value for option "%s".', $name));

        return $this;
    }
}
