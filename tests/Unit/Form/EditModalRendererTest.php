<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Form\EditModalRenderRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
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
    /**
     * @param array<string, mixed> $extraContext
     */
    #[Test]
    #[TestWith(['render', 'modal.html.twig', ['title' => 'Update product', 'entity_label' => null, 'body_template' => 'body.html.twig'], '<div>modal</div>'])]
    #[TestWith(['renderBody', 'body.html.twig', [], '<form>body</form>'])]
    public function it_renders_the_expected_template_with_the_form_view(
        string $method,
        string $expectedTemplate,
        array $extraContext,
        string $expectedHtml,
    ): void {
        $formView = new FormView();
        $form     = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('createView')->willReturn($formView);

        $entity = new \stdClass();

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with($expectedTemplate, ['form' => $formView, 'entity' => $entity] + $extraContext)
            ->willReturn($expectedHtml);

        $renderer = new EditModalRenderer($twig, 'Update product');

        $request = new EditModalRenderRequest(
            form: $form,
            entity: $entity,
            templatePath: 'modal.html.twig',
            bodyTemplatePath: 'body.html.twig',
        );

        $html = match ($method) {
            'render'     => $renderer->render($request),
            'renderBody' => $renderer->renderBody($request),
        };

        $this->assertSame($expectedHtml, $html);
    }

    #[Test]
    public function it_passes_the_entity_label_when_the_entity_is_stringable(): void
    {
        $formView = new FormView();
        $form     = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($formView);

        $entity = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Ada Lovelace';
            }
        };

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('modal.html.twig', [
                'form'          => $formView,
                'entity'        => $entity,
                'title'         => 'Update product',
                'entity_label'  => 'Ada Lovelace',
                'body_template' => 'body.html.twig',
            ])
            ->willReturn('<div>modal</div>');

        $renderer = new EditModalRenderer($twig, 'Update product');

        $renderer->render(new EditModalRenderRequest(
            form: $form,
            entity: $entity,
            templatePath: 'modal.html.twig',
            bodyTemplatePath: 'body.html.twig',
        ));
    }
}
