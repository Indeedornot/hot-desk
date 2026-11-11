<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Yaml;

final class AppController extends AbstractController
{
    #[Route('/api/openapi.json', methods: ['GET'])]
    public function documentation(): JsonResponse
    {
        return $this->json(Yaml::parseFile($this->getParameter('kernel.project_dir').'/docs/openapi.yaml'));
    }

    #[Route('/{path}', requirements: ['path' => '(?!api(?:/|$)|app(?:/|$)).*'], defaults: ['path' => ''], methods: ['GET'], priority: -100)]
    public function index(): Response
    {
        $file = $this->getParameter('kernel.project_dir').'/public/app/index.html';
        if (!is_file($file)) {
            return new Response('Build the frontend with npm --prefix frontend run build.', 503);
        }

        return new Response(file_get_contents($file), 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }
}
