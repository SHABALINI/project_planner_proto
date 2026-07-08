<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Area;
use App\Entity\Task;
use App\Entity\Subtask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard')]
class ProjectController extends AbstractController
{
    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $projects = $em->getRepository(Project::class)->findBy(['owner' => $user]);

        return $this->render('project/dashboard.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/project/create', name: 'api_project_create', methods: ['POST'])]
    public function createProject(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['success' => false], 401);

        $data = json_decode($request->getContent(), true);
        $project = new Project();
        $project->setTitle($data['title']);
        $project->setOwner($user);

        $em->persist($project);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/area/create', name: 'api_area_create', methods: ['POST'])]
    public function createArea(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $project = $em->getRepository(Project::class)->find($data['project_id']);

        $area = new Area();
        $area->setTitle($data['title']);
        $area->setDescription($data['description'] ?? '');
        $area->setProject($project);

        $em->persist($area);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/task/create', name: 'api_task_create', methods: ['POST'])]
    public function createTask(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $area = $em->getRepository(Area::class)->find($data['area_id']);

        $task = new Task();
        $task->setTitle($data['title']);
        $task->setDescription('');
        $task->setPriority('medium');
        $task->setStatus('todo');
        $task->setArea($area);

        $em->persist($task);
        $em->flush();

        return new JsonResponse(['success' => true, 'id' => $task->getId()]);
    }

    #[Route('/subtask/create', name: 'api_subtask_create', methods: ['POST'])]
    public function createSubtask(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $task = $em->getRepository(Task::class)->find($data['task_id']);

        $subtask = new Subtask();
        $subtask->setTitle($data['title']);
        $subtask->setStatus('todo');
        $subtask->setTask($task);

        $em->persist($subtask);
        $em->flush();

        return new JsonResponse(['success' => true, 'id' => $subtask->getId()]);
    }

    // ОБНОВЛЕНИЕ ПАРАМЕТРОВ ЗАДАЧИ
    #[Route('/task/update', name: 'api_task_update', methods: ['POST'])]
    public function updateTask(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $task = $em->getRepository(Task::class)->find($data['task_id']);
        if (!$task) return new JsonResponse(['success' => false], 404);

        if ($data['field'] === 'status') $task->setStatus($data['value']);
        if ($data['field'] === 'priority') $task->setPriority($data['value']);
        if ($data['field'] === 'description') $task->setDescription($data['value']);

        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    // ОБНОВЛЕНИЕ СТАТУСА ПОДЗАДАЧИ
    #[Route('/subtask/update', name: 'api_subtask_update', methods: ['POST'])]
    public function updateSubtask(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $subtask = $em->getRepository(Subtask::class)->find($data['subtask_id']);
        if (!$subtask) return new JsonResponse(['success' => false], 404);

        $subtask->setStatus($data['status']);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    // МАРШРУТЫ ДЛЯ УДАЛЕНИЯ ЭЛЕМЕНТОВ
    #[Route('/delete/{type}/{id}', name: 'api_element_delete', methods: ['POST'])]
    public function deleteElement(string $type, int $id, EntityManagerInterface $em): JsonResponse
    {
        $entity = null;
        if ($type === 'project') $entity = $em->getRepository(Project::class)->find($id);
        if ($type === 'area') $entity = $em->getRepository(Area::class)->find($id);
        if ($type === 'task') $entity = $em->getRepository(Task::class)->find($id);
        if ($type === 'subtask') $entity = $em->getRepository(Subtask::class)->find($id);

        if (!$entity) return new JsonResponse(['success' => false, 'error' => 'Элемент не найден'], 404);

        $em->remove($entity);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}