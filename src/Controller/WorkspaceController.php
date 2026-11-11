<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\BookingInput;
use App\Entity\Office;
use App\Entity\User;
use App\Repository\WorkspaceRepository;
use App\Service\AvailabilityService;
use App\Service\BookingService;
use App\Service\ResponseMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
final class WorkspaceController extends AbstractController
{
    public function __construct(private WorkspaceRepository $repo)
    {
    }

    #[Route('/offices', methods: ['GET'])]
    public function offices(): JsonResponse
    {
        return $this->json(['items' => array_values(array_map(ResponseMapper::office(...), array_filter($this->repo->all(Office::class), fn (Office $o) => $o->active)))]);
    }

    #[Route('/desks', methods: ['GET'])]
    public function desks(#[CurrentUser] User $user, Request $request, AvailabilityService $availability): JsonResponse
    {
        return $this->json($availability->desks($user, $request->query->getInt('officeId'), $request->query->getString('date'), $request->query->getString('q')));
    }

    #[Route('/bookings', methods: ['GET'])]
    #[Route('/admin/bookings', methods: ['GET'])]
    public function bookings(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $result = $this->repo->bookings($user, str_starts_with($request->getPathInfo(), '/api/admin/'), $request->query->all());
        $result['items'] = array_map(ResponseMapper::booking(...), $result['items']);

        return $this->json($result);
    }

    #[Route('/bookings', methods: ['POST'], format: 'json')]
    #[Route('/bookings/{id}', methods: ['PUT'], requirements: ['id' => '\d+'], format: 'json')]
    public function save(#[CurrentUser] User $user, #[MapRequestPayload] BookingInput $input, BookingService $service, ?int $id = null): JsonResponse
    {
        return $this->json(ResponseMapper::booking($service->save($user, $input, $id)), $id ? 200 : 201);
    }

    #[Route('/bookings/{id}/{action}', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'check-in|check-out|cancel'])]
    public function transition(#[CurrentUser] User $user, int $id, string $action, BookingService $service): JsonResponse
    {
        return $this->json(ResponseMapper::booking($service->transition($user, $id, $action)));
    }

    #[Route('/events', methods: ['GET'])]
    #[Route('/admin/events', methods: ['GET'])]
    public function events(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $result = $this->repo->events($user, str_starts_with($request->getPathInfo(), '/api/admin/'), $request->query->all());
        $result['items'] = array_map(ResponseMapper::event(...), $result['items']);

        return $this->json($result);
    }
}
