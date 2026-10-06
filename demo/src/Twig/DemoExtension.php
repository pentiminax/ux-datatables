<?php

declare(strict_types=1);

namespace App\Twig;

use App\Controller\DemoController;
use App\Demo\Page;
use App\Demo\Pages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\String\u;

use Twig\Attribute\AsTwigFunction;

/**
 * Feeds the demo shell: navigation, the current page, and the real source files behind it.
 */
final readonly class DemoExtension
{
    public function __construct(
        private RequestStack $requestStack,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @return array<string, list<Page>>
     */
    #[AsTwigFunction('demo_navigation')]
    public function navigation(): array
    {
        $groups = [];
        foreach (Pages::all() as $page) {
            $groups[$page->group][] = $page;
        }

        return $groups;
    }

    #[AsTwigFunction('demo_page')]
    public function currentPage(): ?Page
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->getString('_route');

        return null === $route ? null : Pages::find($route);
    }

    #[AsTwigFunction('demo_next_page')]
    public function nextPage(Page $page): ?Page
    {
        $pages = Pages::all();
        $index = array_search($page, $pages, false);

        return false === $index ? null : $pages[$index + 1] ?? null;
    }

    /**
     * @return list<array{name: string, language: string, code: string}>
     */
    #[AsTwigFunction('demo_sources')]
    public function sources(Page $page): array
    {
        $table = new \ReflectionClass($page->table);

        $sources = [
            $this->file((string) $table->getFileName()),
            [
                'name'     => 'DemoController.php',
                'language' => 'php',
                'code'     => $this->controllerAction($page),
            ],
            [
                'name'     => 'template.html.twig',
                'language' => 'twig',
                'code'     => '{{ render_datatable(table) }}',
            ],
        ];

        foreach ($page->sources as $path) {
            $sources[] = $this->file($this->projectDir.'/'.$path);
        }

        return $sources;
    }

    /**
     * @return array{name: string, language: string, code: string}
     */
    private function file(string $path): array
    {
        return [
            'name'     => basename($path),
            'language' => match (true) {
                str_ends_with($path, '.twig') => 'twig',
                str_ends_with($path, '.js')   => 'javascript',
                default                       => 'php',
            },
            'code' => rtrim((string) file_get_contents($path)),
        ];
    }

    /**
     * The action method that renders the page, attributes included, dedented.
     */
    private function controllerAction(Page $page): string
    {
        $method = new \ReflectionMethod(DemoController::class, u($page->route)->camel()->toString());
        $lines  = file((string) $method->getFileName(), \FILE_IGNORE_NEW_LINES);
        $start  = $method->getStartLine() - 1;

        while ($start > 0 && str_starts_with(trim($lines[$start - 1]), '#[')) {
            --$start;
        }

        $action = \array_slice($lines, $start, $method->getEndLine() - $start);

        return implode("\n", array_map(static fn (string $line): string => preg_replace('/^ {4}/', '', $line), $action));
    }
}
