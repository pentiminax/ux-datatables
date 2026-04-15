<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Form\EditModalRenderRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(EditModalRenderer::class)]
final class EditModalRendererTest extends TestCase
{
    #[Test]
    public function it_renders_the_full_modal_template_with_expected_context(): void
    {
        $formView = new FormView();
        $form     = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('createView')->willReturn($formView);

        $entity = new \stdClass();
        $twig   = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('modal.html.twig', [
                'form'          => $formView,
                'entity'        => $entity,
                'title'         => 'Edit',
                'body_template' => 'body.html.twig',
            ])
            ->willReturn('<div>modal</div>');

        $renderer = new EditModalRenderer($twig, 'Edit');

        $html = $renderer->render(new EditModalRenderRequest(
            form: $form,
            entity: $entity,
            templatePath: 'modal.html.twig',
            bodyTemplatePath: 'body.html.twig',
        ));

        $this->assertSame('<div>modal</div>', $html);
    }

    #[Test]
    public function it_renders_the_modal_body_template(): void
    {
        $formView = new FormView();
        $form     = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('createView')->willReturn($formView);

        $entity = new \stdClass();
        $twig   = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('body.html.twig', [
                'form'   => $formView,
                'entity' => $entity,
            ])
            ->willReturn('<form>body</form>');

        $renderer = new EditModalRenderer($twig);

        $html = $renderer->renderBody(new EditModalRenderRequest(
            form: $form,
            entity: $entity,
            templatePath: 'modal.html.twig',
            bodyTemplatePath: 'body.html.twig',
        ));

        $this->assertSame('<form>body</form>', $html);
    }
}
