<?php

namespace App\Controller;

use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[Route('/dashboard')]
class TaskController extends AbstractController
{
    public function __construct(private TaskService $taskService) {}

    #[Route('/task/create', name: 'api_task_create', methods: ['POST'])]
    public function createTask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data['title']) || empty($data['area_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing required fields: area_id and title are mandatory.'], 400);
        }

        try {
            $task = $this->taskService->createTask($user, (int)$data['area_id'], $data['title'], $data['description'] ?? null, $data['deadline'] ?? null);
            return new JsonResponse(['success' => true, 'id' => $task->getId()]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    #[Route('/task/update', name: 'api_task_update', methods: ['POST'])]
    public function updateTask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data) || !isset($data['field']) || !isset($data['task_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing required parameter: field'], 400);
        }

        try {
            $this->taskService->updateTask($user, (int)$data['task_id'], $data['field'], $data['value'] ?? null);
            return new JsonResponse(['success' => true]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false], 404);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    #[Route('/task/{id}/subtasks', name: 'api_task_subtasks', methods: ['GET'])]
    public function getTaskSubtasks(int $id): JsonResponse
    {
        try {
            $subtasks = $this->taskService->getTaskSubtasksData($id);
            return new JsonResponse(['success' => true, 'subtasks' => $subtasks]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }
}