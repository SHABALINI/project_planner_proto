<?php

namespace App\Controller;

use App\Service\SubtaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[Route('/dashboard')]
class SubtaskController extends AbstractController
{
    public function __construct(private SubtaskService $subtaskService) {}

    #[Route('/subtask/create', name: 'api_subtask_create', methods: ['POST'])]
    public function createSubtask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data['title']) || empty($data['task_id'])) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required fields: task_id and title are mandatory.']);
        }

        try {
            $subtask = $this->subtaskService->createSubtask($user, (int)$data['task_id'], $data['title'], $data['description'] ?? null);
            return new JsonResponse(['success' => true, 'id' => $subtask->getId()]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/subtask/update', name: 'api_subtask_update', methods: ['POST'])]
    public function updateSubtask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data) || !isset($data['subtask_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing required parameter: subtask_id'], 400);
        }

        try {
            $field = $data['field'] ?? 'status';
            $value = $data['value'] ?? null;
            
            // Для поля description, значение может быть null или пустой строкой
            if ($field === 'description' && $value === null) {
                $value = '';
            }
            
            $isStatusChanged = $this->subtaskService->updateSubtask($user, (int)$data['subtask_id'], $field, $value);

            return new JsonResponse([
                'success' => true,
                'isStatusChanged' => $isStatusChanged
            ]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}