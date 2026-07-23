<?php

namespace App\Controller;

use App\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[Route('/dashboard')]
class ProjectController extends AbstractController
{
    public function __construct(private ProjectService $projectService) {}

    #[Route('', name: 'app_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $projects = $this->projectService->getDashboardProjects($user);

        return $this->render('project/dashboard.html.twig', ['projects' => $projects]);
    }

    #[Route('/project/{id}', name: 'app_project_view')]
    public function viewProject(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $viewData = $this->projectService->getProjectViewData($id, $user);
            return $this->render('project/project_view.html.twig', $viewData);
        } catch (NotFoundHttpException|AccessDeniedHttpException $e) {
            throw $this->createNotFoundException($e->getMessage());
        }
    }

    #[Route('/project/create', name: 'api_project_create', methods: ['POST'])]
    public function createProject(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['title'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing title'], 400);
        }

        try {
            $this->projectService->createProject($user, $data['title']);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
    }

    #[Route('/project/{id}/toggle-pin', name: 'api_project_toggle_pin', methods: ['POST'])]
    public function togglePin(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        try {
            $isPinned = $this->projectService->togglePin($id, $user);
            return new JsonResponse(['success' => true, 'isPinned' => $isPinned]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }
}