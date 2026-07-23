<?php

namespace App\Controller;

use App\Service\AreaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[Route('/dashboard')]
class AreaController extends AbstractController
{
    public function __construct(private AreaService $areaService) {}

    #[Route('/area/create', name: 'api_area_create', methods: ['POST'])]
    public function createArea(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data['title']) || empty($data['project_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing required fields: project_id and title are mandatory.'], 400);
        }

        try {
            $this->areaService->createArea($user, (int)$data['project_id'], $data['title'], $data['description'] ?? '');
            return new JsonResponse(['success' => true]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    #[Route('/area/{id}/tasks', name: 'api_area_tasks', methods: ['GET'])]
    public function getAreaTasks(int $id): JsonResponse
    {
        try {
            $tasks = $this->areaService->getAreaTasksData($id);
            return new JsonResponse(['success' => true, 'tasks' => $tasks]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }
}