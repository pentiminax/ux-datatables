<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Pentiminax\UX\DataTables\Attribute\OptionApplier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(OptionApplier::class)]
final class OptionApplierTest extends TestCase
{
    #[Test]
    public function it_applies_an_option_through_its_setter(): void
    {
        $target = new ApplierTarget();

        (new OptionApplier())->apply($target, ['title' => 'Name']);

        $this->assertSame('Name', $target->title);
    }

    #[Test]
    public function it_falls_back_to_the_bare_fluent_method(): void
    {
        $target = new ApplierTarget();

        (new OptionApplier())->apply($target, ['label' => 'Status']);

        $this->assertSame('Status', $target->label);
    }

    #[Test]
    public function it_prefers_the_setter_over_the_bare_method(): void
    {
        $target = new ApplierTarget();

        (new OptionApplier())->apply($target, ['both' => 'value']);

        $this->assertSame('setter', $target->both);
    }

    #[Test]
    public function an_alias_wins_over_both_conventions(): void
    {
        $target  = new ApplierTarget();
        $applier = new OptionApplier([
            'title' => static function (ApplierTarget $target, string $value): void {
                $target->title = 'aliased:'.$value;
            },
        ]);

        $applier->apply($target, ['title' => 'Name']);

        $this->assertSame('aliased:Name', $target->title);
    }

    #[Test]
    public function it_rejects_an_option_no_convention_resolves(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "unknown" is not supported by');

        (new OptionApplier())->apply(new ApplierTarget(), ['unknown' => true]);
    }

    #[Test]
    public function it_reports_support_without_applying_anything(): void
    {
        $applier = new OptionApplier(['aliased' => static fn (object $target, mixed $value) => null]);

        $this->assertTrue($applier->supports(ApplierTarget::class, 'title'));
        $this->assertTrue($applier->supports(ApplierTarget::class, 'label'));
        $this->assertTrue($applier->supports(ApplierTarget::class, 'aliased'));
        $this->assertFalse($applier->supports(ApplierTarget::class, 'unknown'));
    }
}

final class ApplierTarget
{
    public ?string $title = null;

    public ?string $label = null;

    public ?string $both = null;

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function setBoth(string $value): static
    {
        $this->both = 'setter';

        return $this;
    }

    public function both(string $value): static
    {
        $this->both = 'bare';

        return $this;
    }
}
