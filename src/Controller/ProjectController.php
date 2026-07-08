<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Area;
use App\Entity\Task;
use App\Entity\Subtask;
use App\Entity\ProjectMember; // ИСПРАВЛЕНО: Добавлен импорт новой сущности
use App\Entity\User;          // ИСПРАВЛЕНО: Добавлен импорт сущности User
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard')]
class ProjectController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    // ИСПРАВЛЕНО: Добавлен конструктор для инициализации $this->entityManager
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    // ИСПРАВЛЕНО: роут изменен на '', чтобы адрес главного экрана был строго /dashboard
    #[Route('', name: 'app_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login'); // Если не авторизован, отправляем на логин
        }
        
        // Получаем только проекты текущего пользователя
        $projects = $this->entityManager->getRepository(Project::class)->findBy(['owner' => $user]);

        return $this->render('project/dashboard.html.twig', [
            'projects' => $projects,
        ]);
    }

    // 2. Новый роут для открытия конкретного проекта по его ID
    #[Route('/project/{id}', name: 'app_project_view')]
    public function viewProject(int $id): Response
    {
        $user = $this->getUser();
        $project = $this->entityManager->getRepository(Project::class)->findOneBy([
            'id' => $id,
            'owner' => $user // Защита: чужой пользователь не сможет открыть проект по ID
        ]);

        if (!$project) {
            throw $this->createNotFoundException('Проект не найден или у вас нет к нему доступа.');
        }

        return $this->render('project/project_view.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/project/create', name: 'api_project_create', methods: ['POST'])]
    public function createProject(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['success' => false], 401);

        $data = json_decode($request->getContent(), true);
        if (empty($data['title'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing title'], 400);
        }

        $project = new Project();
        $project->setTitle($data['title']);
        $project->setOwner($user);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        // Автоматически добавляем создателя как Админа проекта
        $member = new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole('admin'); 

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/area/create', name: 'api_area_create', methods: ['POST'])]
    public function createArea(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $project = $this->entityManager->getRepository(Project::class)->find($data['project_id']);

        if (!$project) {
            return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);
        }

        $area = new Area();
        $area->setTitle($data['title']);
        $area->setDescription($data['description'] ?? '');
        $area->setProject($project);

        $this->entityManager->persist($area);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/task/create', name: 'app_task_create', methods: ['POST'])]
    public function createTask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['title']) || empty($data['area_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing data'], 400);
        }

        $area = $this->entityManager->getRepository(Area::class)->find($data['area_id']);
        if (!$area) {
            return new JsonResponse(['success' => false, 'error' => 'Area not found'], 404);
        }

        $task = new Task();
        $task->setTitle($data['title']);
        $task->setArea($area);
        $task->setStatus('todo'); 
        $task->setPriority('medium'); 

        if (!empty($data['deadline']) && trim($data['deadline']) !== '') {
            try {
                $task->setDeadline(new \DateTime($data['deadline']));
            } catch (\Exception $e) {
                $task->setDeadline(null);
            }
        } else {
            $task->setDeadline(null);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/subtask/create', name: 'api_subtask_create', methods: ['POST'])]
    public function createSubtask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $task = $this->entityManager->getRepository(Task::class)->find($data['task_id']);

        if (!$task) {
            return new JsonResponse(['success' => false, 'error' => 'Task not found'], 404);
        }

        $subtask = new Subtask();
        $subtask->setTitle($data['title']);
        $subtask->setStatus('todo');
        $subtask->setTask($task);

        $this->entityManager->persist($subtask);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'id' => $subtask->getId()]);
    }

    #[Route('/task/update', name: 'app_task_update', methods: ['POST'])]
    public function updateTask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['task_id']) || empty($data['field'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing data'], 400);
        }

        $task = $this->entityManager->getRepository(Task::class)->find($data['task_id']);
        if (!$task) {
            return new JsonResponse(['success' => false, 'error' => 'Task not found'], 404);
        }

        $field = $data['field'];
        $value = $data['value'];

        if ($field === 'title') {
            $task->setTitle($value);
        } elseif ($field === 'description') {
            $task->setDescription($value);
        } elseif ($field === 'status') {
            $task->setStatus($value);
        } elseif ($field === 'priority') {
            $task->setPriority($value);
        } elseif ($field === 'deadline') {
            if (!empty($value) && trim($value) !== '') {
                try {
                    $task->setDeadline(new \DateTime($value));
                } catch (\Exception $e) {
                    return new JsonResponse(['success' => false, 'error' => 'Invalid date format'], 400);
                }
            } else {
                $task->setDeadline(null);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/subtask/update', name: 'api_subtask_update', methods: ['POST'])]
    public function updateSubtask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $subtask = $this->entityManager->getRepository(Subtask::class)->find($data['subtask_id']);
        if (!$subtask) return new JsonResponse(['success' => false], 404);

        $subtask->setStatus($data['status']);
        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/delete/{type}/{id}', name: 'api_element_delete', methods: ['POST'])]
    public function deleteElement(string $type, int $id): JsonResponse
    {
        $entity = null;
        if ($type === 'project') $entity = $this->entityManager->getRepository(Project::class)->find($id);
        if ($type === 'area') $entity = $this->entityManager->getRepository(Area::class)->find($id);
        if ($type === 'task') $entity = $this->entityManager->getRepository(Task::class)->find($id);
        if ($type === 'subtask') $entity = $this->entityManager->getRepository(Subtask::class)->find($id);

        if (!$entity) return new JsonResponse(['success' => false, 'error' => 'Элемент не найден'], 404);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    // ИСПРАВЛЕНО: Хелпер-метод для будущей проверки прав доступа
    private function getMemberAccess(Project $project, User $user): ?ProjectMember
    {
        if ($project->getOwner() === $user) {
            $member = new ProjectMember();
            $member->setRole('admin');
            return $member;
        }

        return $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user
        ]);
    }

    // ПОЛУЧЕНИЕ СПИСКА ВСЕХ ПОЛЬЗОВАТЕЛЕЙ И ИХ ТЕКУЩИХ РОЛЕЙ/ГАЛОЧЕК В ПРОЕКТЕ
    #[Route('/project/{id}/members', name: 'api_project_members', methods: ['GET'])]
    public function getProjectMembers(int $id): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);

        // Все зарегистрированные пользователи сайта
        $allUsers = $this->entityManager->getRepository(User::class)->findAll();
        
        // Текущие участники проекта
        $members = $this->entityManager->getRepository(ProjectMember::class)->findBy(['project' => $project]);
        
        $membersMap = [];
        foreach ($members as $m) {
            // Собираем ID привязанных сущностей для этого юзера
            $areaIds = array_map(fn($a) => $a->getId(), $m->getAreas()->toArray());
            $taskIds = array_map(fn($t) => $t->getId(), $m->getTasks()->toArray());
            $subtaskIds = array_map(fn($s) => $s->getId(), $m->getSubtasks()->toArray());

            $membersMap[$m->getUser()->getId()] = [
                'role' => $m->getRole(),
                'areas' => $areaIds,
                'tasks' => $taskIds,
                'subtasks' => $subtaskIds
            ];
        }

        $result = [];
        foreach ($allUsers as $u) {
            // Исключаем создателя (владельца) проекта из списка редактирования, он всегда суперадмин
            if ($project->getOwner() === $u) {
                continue;
            }

            $hasMemberData = $membersMap[$u->getId()] ?? null;

            $result[] = [
                'id' => $u->getId(),
                'email' => method_exists($u, 'getEmail') ? $u->getEmail() : $u->getUserIdentifier(),
                'isMember' => $hasMemberData !== null,
                'role' => $hasMemberData ? $hasMemberData['role'] : 'viewer',
                'areas' => $hasMemberData ? $hasMemberData['areas'] : [],
                'tasks' => $hasMemberData ? $hasMemberData['tasks'] : [],
                'subtasks' => $hasMemberData ? $hasMemberData['subtasks'] : []
            ];
        }

        return new JsonResponse($result);
    }

    // СОХРАНЕНИЕ РОЛИ И ВСЕХ СВЯЗЕЙ (С АЛГОРИТМОМ СКАЧИВАНИЯ ГАЛОЧЕК ВНИЗ)
    #[Route('/project/{id}/member/save', name: 'api_project_member_save', methods: ['POST'])]
    public function saveProjectMember(int $id, Request $request): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);

        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $role = $data['role'] ?? 'viewer';
        $selectedAreas = $data['areas'] ?? [];
        $selectedTasks = $data['tasks'] ?? [];
        $selectedSubtasks = $data['subtasks'] ?? [];

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) return new JsonResponse(['success' => false, 'error' => 'User not found'], 404);

        // Ищем существующего участника или создаем нового
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user
        ]);

        if (!$member) {
            $member = new ProjectMember();
            $member->setProject($project);
            $member->setUser($user);
        }

        $member->setRole($role);

        // Очищаем старые связи перед сохранением новых
        foreach ($member->getAreas() as $a) $member->removeArea($a);
        foreach ($member->getTasks() as $t) $member->removeTask($t);
        foreach ($member->getSubtasks() as $s) $member->removeSubtask($s);

        // --- РЕАЛИЗАЦИЯ АЛГОРИТМА ТЗ ---
        
        // Перебираем все области проекта, чтобы применить правила каскадных галочек
        foreach ($project->getAreas() as $area) {
            $isAreaChecked = in_array($area->getId(), $selectedAreas);

            // 1. Руководителей привязываем исключительно на области
            if ($isAreaChecked && $role === 'manager') {
                $member->addArea($area);
            }

            foreach ($area->getTasks() as $task) {
                $isTaskChecked = in_array($task->getId(), $selectedTasks);

                // 2. Если Исполнитель назначен на ОБЛАСТЬ -> автопривязка ко всем задачам и подзадачам внутри
                // Либо если он явно выбран для этой ЗАДАЧИ
                if (($isAreaChecked || $isTaskChecked) && $role === 'executor') {
                    $member->addTask($task);
                    $member->addArea($area); // Для связности сохраним и область
                }

                foreach ($task->getSubtasks() as $subtask) {
                    $isSubtaskChecked = in_array($subtask->getId(), $selectedSubtasks);

                    // 3. Если Исполнитель привязан к области или к задаче -> автопривязка к подзадачам
                    // Либо если галочка проставлена на саму ПОДЗАДАЧУ
                    if (($isAreaChecked || $isTaskChecked || $isSubtaskChecked) && $role === 'executor') {
                        $member->addSubtask($subtask);
                    }
                }
            }
        }

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}