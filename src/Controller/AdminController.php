<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AssignmentInput;
use App\Dto\DeskInput;
use App\Dto\OfficeInput;
use App\Dto\UserInput;
use App\Entity\Desk;
use App\Entity\Office;
use App\Entity\SeatAssignment;
use App\Entity\User;
use App\Repository\WorkspaceRepository;
use App\Service\AdminService;
use App\Service\ResponseMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/admin')]
final class AdminController extends AbstractController
{
    public function __construct(private AdminService $service, private WorkspaceRepository $repo)
    {
    }

    #[Route('/offices', methods: ['GET'])]
    public function offices(): JsonResponse
    {
        return $this->json(['items' => array_map(ResponseMapper::office(...), $this->repo->all(Office::class))]);
    }

    #[Route('/desks', methods: ['GET'])]
    public function desks(): JsonResponse
    {
        return $this->json(['items' => array_map(ResponseMapper::desk(...), $this->repo->all(Desk::class))]);
    }

    #[Route('/users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        $result = $this->repo->users($request->query->all());
        $result['items'] = array_map(ResponseMapper::user(...), $result['items']);

        return $this->json($result);
    }

    #[Route('/offices', methods: ['POST'], format: 'json')]
    #[Route('/offices/{id}', methods: ['PUT'], requirements: ['id' => '\d+'], format: 'json')]
    public function office(#[CurrentUser] User $actor, #[MapRequestPayload] OfficeInput $input, ?int $id = null): JsonResponse
    {
        return $this->json(ResponseMapper::office($this->service->office($actor, $input, $id)), $id ? 200 : 201);
    }

    #[Route('/desks', methods: ['POST'], format: 'json')]
    #[Route('/desks/{id}', methods: ['PUT'], requirements: ['id' => '\d+'], format: 'json')]
    public function desk(#[CurrentUser] User $actor, #[MapRequestPayload] DeskInput $input, ?int $id = null): JsonResponse
    {
        return $this->json(ResponseMapper::desk($this->service->desk($actor, $input, $id)), $id ? 200 : 201);
    }

    #[Route('/users', methods: ['POST'], format: 'json')]
    #[Route('/users/{id}', methods: ['PUT'], requirements: ['id' => '\d+'], format: 'json')]
    public function user(#[CurrentUser] User $actor, #[MapRequestPayload] UserInput $input, ?int $id = null): JsonResponse
    {
        return $this->json(ResponseMapper::user($this->service->user($actor, $input, $id)), $id ? 200 : 201);
    }

    #[Route('/{resource}/{id}', methods: ['DELETE'], requirements: ['resource' => 'offices|desks|users', 'id' => '\d+'])]
    public function delete(#[CurrentUser] User $actor, string $resource, int $id): JsonResponse
    {
        $this->service->delete($actor, $resource, $id);

        return $this->json(['message' => 'Deleted.']);
    }

    #[Route('/assignments', methods: ['GET'])]
    public function assignments(): JsonResponse
    {
        return $this->json(['items' => array_map(ResponseMapper::assignment(...), $this->repo->all(SeatAssignment::class))]);
    }

    #[Route('/assignments', methods: ['POST'], format: 'json')]
    public function assign(#[CurrentUser] User $actor, #[MapRequestPayload] AssignmentInput $input): JsonResponse
    {
        return $this->json(ResponseMapper::assignment($this->service->assign($actor, $input)), 201);
    }

    #[Route('/assignments/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function unassign(#[CurrentUser] User $actor, int $id): JsonResponse
    {
        $this->service->unassign($actor, $id);

        return $this->json(['message' => 'Assignment removed.']);
    }
}
