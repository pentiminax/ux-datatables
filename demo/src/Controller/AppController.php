<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoSeeder;
use App\Demo\Pages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class AppController extends AbstractController
{
    #[Route('/', name: 'root')]
    public function root(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('overview', [
            '_locale' => $request->getPreferredLanguage(['en', 'fr'])]
        );
    }

    #[Route('/{_locale}/reset', name: 'reset', requirements: ['_locale' => 'en|fr'], methods: ['POST'])]
    #[IsCsrfTokenValid('reset-demo')]
    public function reset(Request $request, DemoSeeder $seeder): RedirectResponse
    {
        $seeder->reset();

        $this->addFlash('success', 'flash.reset');

        $page = Pages::find($request->getPayload()->getString('page')) ?? Pages::all()[0];

        return $this->redirectToRoute($page->route);
    }
}
