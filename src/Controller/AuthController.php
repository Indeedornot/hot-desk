<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\ResponseMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    #[Route('/csrf', methods: ['GET'])]
    public function csrf(CsrfTokenManagerInterface $csrf): JsonResponse
    {
        return $this->json(['csrfToken' => $csrf->getToken('api')->getValue()])->setPrivate()->setMaxAge(0);
    }

    #[Route('/login', methods: ['POST'])]
    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user, CsrfTokenManagerInterface $csrf): JsonResponse
    {
        return $this->json(['user' => ResponseMapper::user($user), 'csrfToken' => $csrf->getToken('api')->getValue()]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request, TokenStorageInterface $tokens): JsonResponse
    {
        $tokens->setToken(null);
        $request->getSession()->invalidate();

        return $this->json(['message' => 'Signed out.']);
    }
}
