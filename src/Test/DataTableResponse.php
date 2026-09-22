<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Test;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * The decoded payload of a server-side DataTable response.
 */
final class DataTableResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        private readonly array $payload,
    ) {
    }

    /**
     * @param class-string $dataTableClass named in the failure messages, so a test reads which
     *                                     table answered badly
     */
    public static function fromResponse(Response $response, string $dataTableClass): self
    {
        $content = (string) $response->getContent();

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            Assert::fail(\sprintf(
                'The Ajax request for "%s" returned HTTP %d instead of 200. Body: %s',
                $dataTableClass,
                $response->getStatusCode(),
                self::truncate($content),
            ));
        }

        $payload = json_decode($content, true);

        if (!\is_array($payload)) {
            Assert::fail(\sprintf(
                'The Ajax response for "%s" is not a JSON object. Body: %s',
                $dataTableClass,
                self::truncate($content),
            ));
        }

        return new self($payload);
    }

    public function draw(): ?int
    {
        $draw = $this->payload['draw'] ?? null;

        return null === $draw ? null : (int) $draw;
    }

    public function recordsTotal(): int
    {
        return (int) ($this->payload['recordsTotal'] ?? 0);
    }

    public function recordsFiltered(): int
    {
        return (int) ($this->payload['recordsFiltered'] ?? 0);
    }

    /**
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
            Assert::fail(\sprintf('Row %d does not exist, the response carries %d row(s).', $index, \count($rows)));
        }

        return $rows[$index];
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
        Assert::assertCount($expected, $this->rows(), 'Unexpected number of rows in the DataTable response.');

        return $this;
    }

    public function assertRecordsTotal(int $expected): self
    {
        Assert::assertSame($expected, $this->recordsTotal(), 'Unexpected recordsTotal in the DataTable response.');

        return $this;
    }

    public function assertRecordsFiltered(int $expected): self
    {
        Assert::assertSame($expected, $this->recordsFiltered(), 'Unexpected recordsFiltered in the DataTable response.');

        return $this;
    }

    /**
     * @param array<string, mixed> $subset a row matches when it carries every expected key and value
     */
    public function assertRowsContain(array $subset): self
    {
        foreach ($this->rows() as $row) {
            if ($subset === array_intersect_key($row, $subset)) {
                Assert::assertTrue(true);

                return $this;
            }
        }

        Assert::fail(\sprintf('No row matches %s.', json_encode($subset)));
    }

    /**
     * @param list<mixed> $expected in row order
     */
    public function assertColumnValues(string $column, array $expected): self
    {
        $rows = $this->rows();

        if ([] !== $rows && !\array_key_exists($column, $rows[0])) {
            Assert::fail(\sprintf('Column "%s" is absent from the rows. Available columns: %s.', $column, implode(', ', array_keys($rows[0]))));
        }

        Assert::assertSame($expected, array_column($rows, $column), \sprintf('Unexpected values for column "%s".', $column));

        return $this;
    }

    private static function truncate(string $content): string
    {
        return \strlen($content) > 200 ? substr($content, 0, 200).'...' : $content;
    }
}
