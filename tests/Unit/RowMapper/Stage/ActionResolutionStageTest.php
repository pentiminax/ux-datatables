<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\RowMapper\Stage\ActionResolutionStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ActionResolutionStage::class)]
final class ActionResolutionStageTest extends TestCase
{
    #[Test]
    public function it_resolves_action_urls(): void
    {
        $actions = (new Actions())
            ->add(Action::detail()->linkToUrl(static fn (array $row): string => '/items/'.$row['id']));

        $stage = new ActionResolutionStage(new ActionRowDataResolver());

        $result = $stage->process(
            ['id' => 3],
            ['id' => 3],
            [
                TextColumn::new('id'),
                ActionColumn::fromActions('actions', 'Actions', $actions),
            ],
        );

        $this->assertSame('/items/3', $result[ActionRowDataResolver::ROW_ACTIONS_KEY]['DETAIL']['url']);
    }

    #[Test]
    public function it_passes_through_when_no_action_column(): void
    {
        $stage  = new ActionResolutionStage(new ActionRowDataResolver());
        $result = $stage->process(['id' => 1], ['id' => 1], [TextColumn::new('id')]);

        $this->assertSame(['id' => 1], $result);
    }
}
