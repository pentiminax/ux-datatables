<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Dto\ColumnDto;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractColumn::class)]
final class AbstractColumnTest extends TestCase
{
    #[Test]
    public function it_delegates_json_serialization_to_the_dto(): void
    {
        $dto = (new ColumnDto())
            ->setType(ColumnType::STRING)
            ->setName('status')
            ->setTitle('Status')
            ->setActions([['type' => 'DETAIL', 'url' => '/books/42']]);

        $column = new class($dto) extends AbstractColumn {};

        $this->assertSame($dto->jsonSerialize(), $column->jsonSerialize());
    }
}
