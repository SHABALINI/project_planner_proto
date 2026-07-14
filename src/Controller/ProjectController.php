<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Area;
use App\Entity\Task;
use App\Entity\Comment;
use App\Entity\Subtask;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;


#[Route('/dashboard')]
class ProjectController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private NotificationService $notificationService;

    public function __construct(EntityManagerInterface $entityManager,NotificationService $notificationService){
        $this->entityManager = $entityManager;
        $this->notificationService = $notificationService;
    }

    #[Route('', name: 'app_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        $ownedProjects = $this->entityManager->getRepository(Project::class)->findBy(['owner' => $user]);
        $memberships = $this->entityManager->getRepository(ProjectMember::class)->findBy(['user' => $user]);
        
        $joinedProjects = [];
        foreach ($memberships as $membership) {
            $project = $membership->getProject();
            if ($project && $project->getOwner() !== $user) {
                $joinedProjects[] = $project;
            }
        }
        // Объединяем все проекты
            $allProjects = array_merge($ownedProjects, $joinedProjects);

            // Сортируем: сначала закрепленные, потом по дате создания
        usort($allProjects, function($a, $b) {
            // Сначала закрепленные
            if ($a->isPinned() && !$b->isPinned()) {
                return -1;
            }
            if (!$a->isPinned() && $b->isPinned()) {
                return 1;
            }
            // Если оба закреплены или оба нет - по дате создания (новые сверху)
            return $b->getId() <=> $a->getId();
        });

        return $this->render('project/dashboard.html.twig', [
            'projects' => $allProjects,
        ]);
    }

    #[Route('/project/{id}', name: 'app_project_view')]
    public function viewProject(int $id): Response
    {
        $user = $this->getUser();
        $allUsers = $this->entityManager->getRepository(User::class)->findAll();
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        
        if (!$project) {
            throw $this->createNotFoundException('Проект не найден.');
        }

        $isOwner = ($project->getOwner() === $user);
        $memberInfo = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user
        ]);

        if (!$isOwner && !$memberInfo) {
            throw $this->createNotFoundException('У вас нет доступа к этому проекту.');
        }

        // Определяем текстовую роль для Twig
        $role = 'viewer';
        if ($isOwner) {
            $role = 'admin';
        } elseif ($memberInfo) {
            $role = $memberInfo->getRole();
        }

        // Собираем массивы разрешенных ID для Руководителей и Исполнителей
        $allowedAreas = $memberInfo ? array_map(fn($a) => $a->getId(), $memberInfo->getAreas()->toArray()) : [];
        $allowedTasks = $memberInfo ? array_map(fn($t) => $t->getId(), $memberInfo->getTasks()->toArray()) : [];
        $allowedSubtasks = $memberInfo ? array_map(fn($s) => $s->getId(), $memberInfo->getSubtasks()->toArray()) : [];

        $allMembers = $this->entityManager->getRepository(ProjectMember::class)->findBy(['project' => $project]);
        $projectMembers = array_filter($allMembers, function($member) use ($project) {
            // Исключаем владельца проекта
            return $member->getUser()->getId() !== $project->getOwner()->getId();
        });;

        // Сортируем по приоритету ролей
        $rolePriority = [
            'admin' => 1,
            'manager' => 2,
            'executor' => 3,
            'viewer' => 4
        ];

        usort($projectMembers, function($a, $b) use ($rolePriority) {
            return $rolePriority[$a->getRole()] <=> $rolePriority[$b->getRole()];
        });

        return $this->render('project/project_view.html.twig', [
            'project' => $project,
            'userRole' => $role,
            'allowedAreas' => $allowedAreas,
            'allowedTasks' => $allowedTasks,
            'allowedSubtasks' => $allowedSubtasks,
            'currentUser' => $user,
            'allUsers' => $allUsers,
            'project_members' => $projectMembers,
        ]);
    }

    #[Route('project/create', name: 'api_project_create', methods: ['POST'])]
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
            $project = new Project();
            $project->setTitle($data['title']);
            $project->setOwner($user);

            $this->entityManager->persist($project);
            $this->entityManager->flush(); // Сначала сохраняем чистый проект

            // Создаем запись админа только ЕСЛИ класс ProjectMember полностью готов принимать данные
            if (class_exists(ProjectMember::class)) {
                $member = new ProjectMember();
                $member->setProject($project);
                $member->setUser($user);
                $member->setRole('admin');
                
                $this->entityManager->persist($member);
                $this->entityManager->flush();
            }

            return new JsonResponse(['success' => true]);
            
        } catch (\Exception $e) {
            // Если что-то пойдет не так, бэкенд не промолчит, а вернет точный текст ошибки базы данных
            return new JsonResponse([
                'success' => false, 
                'error' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/project/{id}/toggle-pin', name: 'api_project_toggle_pin', methods: ['POST'])]
    public function togglePin(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) {
            return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);
        }

        // Проверяем, есть ли у пользователя доступ к проекту
        $isOwner = ($project->getOwner() === $user);
        $memberInfo = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project,
            'user' => $user
        ]);

        if (!$isOwner && !$memberInfo) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        // Переключаем статус закрепления
        $project->setIsPinned(!$project->isPinned());
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'isPinned' => $project->isPinned()
        ]);
    }


    #[Route('/area/create', name: 'api_area_create', methods: ['POST'])]
    public function createArea(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $project = $this->entityManager->getRepository(Project::class)->find($data['project_id'] ?? 0);

        if (!$project) return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);

        // ПРОВЕРКА ПРАВ: Только Админ может создавать области
        if ($project->getOwner() !== $user) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            if (!$member || $member->getRole() !== 'admin') {
                return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
            }
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
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $area = $this->entityManager->getRepository(Area::class)->find($data['area_id'] ?? 0);
        if (!$area) return new JsonResponse(['success' => false, 'error' => 'Area not found'], 404);

        $project = $area->getProject();
        
        if ($project->getOwner() !== $user) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            if (!$member || ($member->getRole() !== 'admin' && ($member->getRole() !== 'manager' || !$member->getAreas()->contains($area)))) {
                return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
            }
        }

        $task = new Task();
        $task->setTitle($data['title']);
        $task->setDescription($data['description'] ?? null);
        $task->setArea($area);
        $task->setStatus('todo');
        $task->setPriority('medium');

        if (!empty($data['deadline'])) {
            $task->setDeadline(new \DateTime($data['deadline']));
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        try {
            $this->notificationService->notifyNewTask($task, $user);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            error_log('Notification error: ' . $e->getMessage());
        }

        return new JsonResponse(['success' => true, 'id' => $task->getId()]);
    }

    #[Route('/subtask/create', name: 'api_subtask_create', methods: ['POST'])]
    public function createSubtask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $task = $this->entityManager->getRepository(Task::class)->find($data['task_id'] ?? 0);
        if (!$task) return new JsonResponse(['success' => false, 'error' => 'Task not found'], 404);

        $project = $task->getArea()->getProject();

        if ($project->getOwner() !== $user) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            if (!$member || ($member->getRole() !== 'admin' && ($member->getRole() !== 'manager' || !$member->getAreas()->contains($task->getArea())))) {
                return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
            }
        }

        $subtask = new Subtask();
        $subtask->setTitle($data['title']);
        $subtask->setDescription($data['description'] ?? null);
        $subtask->setStatus('todo');
        $subtask->setTask($task);

        $this->entityManager->persist($subtask);
        $this->entityManager->flush();

        try {
            $this->notificationService->notifyNewSubtask($subtask, $user);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            error_log('Notification error: ' . $e->getMessage());
        }

        return new JsonResponse(['success' => true, 'id' => $subtask->getId()]);
    }

    #[Route('/task/update', name: 'app_task_update', methods: ['POST'])]
    public function updateTask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $task = $this->entityManager->getRepository(Task::class)->find($data['task_id'] ?? 0);
        if (!$task) return new JsonResponse(['success' => false], 404);

        $project = $task->getArea()->getProject();
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        $field = $data['field'];
        $value = $data['value'];

        if ($role === 'viewer') {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }
        if ($role === 'executor' && $field !== 'status' && $field !== 'description') { // ← Разрешаем executor менять описание
            return new JsonResponse(['success' => false, 'error' => 'Executors can only change status and description'], 403);
        }
        if ($role === 'manager' && !$member->getAreas()->contains($task->getArea())) {
            return new JsonResponse(['success' => false, 'error' => 'Not your area'], 403);
        }

        $changeText = null;

        if ($field === 'status') {
            $task->setStatus($value);
            $statusRu = ($value === 'done' ? 'Выполнено' : ($value === 'progress' ? 'В работе' : 'Не выполнено'));
            $changeText = "→ статус «" . $statusRu . "» у задачи «" . $task->getTitle() . "»";
        } elseif ($field === 'priority') {
            $task->setPriority($value);
            $changeText = "→ приоритет «" . $value . "» у задачи «" . $task->getTitle() . "»";
        } elseif ($field === 'deadline') {
            if (!empty($value)) {
                $task->setDeadline(new \DateTime($value));
                $changeText = "→ дедлайн " . date('d.m.Y', strtotime($value)) . " у задачи «" . $task->getTitle() . "»";
            } else {
                $task->setDeadline(null);
                $changeText = "удалил(а) дедлайн у задачи «" . $task->getTitle() . "»";
            }
        } elseif ($field === 'description') {
            $task->setDescription($value ?: null);
            $changeText = "→ обновил(а) описание у задачи «" . $task->getTitle() . "»";
        }

        $this->entityManager->flush();

        if ($changeText !== null) {
            try {
                $this->notificationService->notifyTaskChange($task, $user, $changeText);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                error_log('Notification error: ' . $e->getMessage());
            }
        }

        return new JsonResponse(['success' => true]);
    }

    
    #[Route('/subtask/update', name: 'api_subtask_update', methods: ['POST'])]
    public function updateSubtask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $subtask = $this->entityManager->getRepository(Subtask::class)->find($data['subtask_id'] ?? 0);
        if (!$subtask) return new JsonResponse(['success' => false], 404);

        $project = $subtask->getTask()->getArea()->getProject();
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        if ($role === 'viewer') return new JsonResponse(['success' => false], 403);
        if ($role === 'manager' && !$member->getAreas()->contains($subtask->getTask()->getArea())) return new JsonResponse(['success' => false], 403);

        $field = $data['field'] ?? 'status';
        $value = $data['value'] ?? 'todo';

        // === ДОБАВЛЯЕМ ОБРАБОТКУ DESCRIPTION ===
        if ($field === 'description') {
            $subtask->setDescription($value ?: null);
            $this->entityManager->flush();
            return new JsonResponse(['success' => true]);
        }

        // === ОСТАЛЬНЫЕ ПОЛЯ ===
        if ($field === 'status') {
            $oldStatus = $subtask->getStatus();
            $newStatus = $value;
            $subtask->setStatus($newStatus);
            $this->entityManager->flush();

            if ($oldStatus !== $newStatus) {
                try {
                    $statusRu = ($newStatus === 'done' ? 'Выполнено' : 'Не выполнено');
                    $changeText = "→ статус «" . $statusRu . "» у подзадачи «" . $subtask->getTitle() . "»";
                    $this->notificationService->notifySubtaskChange($subtask, $user, $changeText);
                    $this->entityManager->flush();
                } catch (\Exception $e) {
                    error_log('Notification error: ' . $e->getMessage());
                }
            }

            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'error' => 'Unknown field']);
    }

    #[Route('/delete/{type}/{id}', name: 'api_element_delete', methods: ['POST'])]
    public function deleteElement(string $type, int $id): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            }

            $entity = null;
            
            switch ($type) {
                case 'project':
                    $entity = $this->entityManager->getRepository(Project::class)->find($id);
                    break;
                case 'area':
                    $entity = $this->entityManager->getRepository(Area::class)->find($id);
                    break;
                case 'task':
                    $entity = $this->entityManager->getRepository(Task::class)->find($id);
                    break;
                case 'subtask':
                    $entity = $this->entityManager->getRepository(Subtask::class)->find($id);
                    break;
                default:
                    return new JsonResponse(['success' => false, 'error' => 'Invalid type: ' . $type], 400);
            }

            if (!$entity) {
                return new JsonResponse(['success' => false, 'error' => 'Entity not found'], 404);
            }

            // Находим проект для проверки прав
            if ($type === 'project') {
                $project = $entity;
            } elseif ($type === 'area') {
                $project = $entity->getProject();
            } elseif ($type === 'task') {
                $project = $entity->getArea()->getProject();
            } elseif ($type === 'subtask') {
                $project = $entity->getTask()->getArea()->getProject();
            } else {
                return new JsonResponse(['success' => false, 'error' => 'Invalid type'], 400);
            }

            // Проверяем права
            $isOwner = ($project->getOwner() === $user);
            $member = $this->entityManager->getRepository(ProjectMember::class)
                ->findOneBy(['project' => $project, 'user' => $user]);
            $role = $isOwner ? 'admin' : ($member ? $member->getRole() : 'viewer');

            if ($role === 'viewer' || $role === 'executor') {
                return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
            }

            // Для менеджера проверяем, что он управляет своей областью
            if ($role === 'manager' && $type === 'area' && !$member->getAreas()->contains($entity)) {
                return new JsonResponse(['success' => false, 'error' => 'Not your area'], 403);
            }

            // Удаляем
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
            
        } catch (\Exception $e) {
            // Логируем ошибку
            error_log('Delete error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new JsonResponse([
                'success' => false, 
                'error' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/area/{id}/tasks', name: 'api_area_tasks', methods: ['GET'])]
    public function getAreaTasks(int $id): JsonResponse
    {
        $area = $this->entityManager->getRepository(Area::class)->find($id);
        if (!$area) {
            return new JsonResponse(['success' => false, 'error' => 'Area not found'], 404);
        }
        
        $tasks = [];
        foreach ($area->getTasks() as $task) {
            $tasks[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus(),
                'priority' => $task->getPriority(),
                'deadline' => $task->getDeadline() ? $task->getDeadline()->format('Y-m-d') : null,
                'subtasks' => $task->getSubtasks()->count(),
                'doneSubtasks' => array_reduce($task->getSubtasks()->toArray(), function($carry, $sub) {
                    return $carry + ($sub->getStatus() === 'done' ? 1 : 0);
                }, 0)
            ];
        }
        
        return new JsonResponse(['success' => true, 'tasks' => $tasks]);
    }

    #[Route('/task/{id}/subtasks', name: 'api_task_subtasks', methods: ['GET'])]
    public function getTaskSubtasks(int $id): JsonResponse
    {
        $task = $this->entityManager->getRepository(Task::class)->find($id);
        if (!$task) {
            return new JsonResponse(['success' => false, 'error' => 'Task not found'], 404);
        }
        
        $subtasks = [];
        foreach ($task->getSubtasks() as $subtask) {
            $subtasks[] = [
                'id' => $subtask->getId(),
                'title' => $subtask->getTitle(),
                'status' => $subtask->getStatus()
            ];
        }
        
        return new JsonResponse(['success' => true, 'subtasks' => $subtasks]);
    }

   #[Route('/project/{id}/members', name: 'api_project_members', methods: ['GET'])]
    public function getProjectMembers(int $id): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) {
            return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);
        }

        $allUsers = $this->entityManager->getRepository(User::class)->findAll();
        $members = $this->entityManager->getRepository(ProjectMember::class)->findBy(['project' => $project]);
        
        // Создаем карту участников
        $membersMap = [];
        foreach ($members as $m) {
            $membersMap[$m->getUser()->getId()] = [
                'role' => $m->getRole(),
                'areas' => array_map(fn($a) => $a->getId(), $m->getAreas()->toArray()),
                'tasks' => array_map(fn($t) => $t->getId(), $m->getTasks()->toArray()),
                'subtasks' => array_map(fn($s) => $s->getId(), $m->getSubtasks()->toArray())
            ];
        }

        $result = [];
        foreach ($allUsers as $u) {
            // Пропускаем владельца проекта (он всегда в проекте)
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
        
        // Добавляем логирование
        error_log('Returning ' . count($result) . ' users for members modal');
        
        return new JsonResponse($result);
    }

    #[Route('/project/{id}/member/save', name: 'api_project_member_save', methods: ['POST'])]
    public function saveProjectMember(int $id, Request $request): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) return new JsonResponse(['success' => false], 404);

        $currentUser = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $user = $this->entityManager->getRepository(User::class)->find($data['user_id'] ?? 0);
        if (!$user) return new JsonResponse(['success' => false], 404);

        // Проверка прав на изменение админов
        $targetMember = $this->entityManager->getRepository(ProjectMember::class)
            ->findOneBy(['project' => $project, 'user' => $user]);
        
        $isOwner = ($project->getOwner() === $currentUser);
        $isTargetAdmin = $targetMember && $targetMember->getRole() === 'admin';
        $newRole = $data['role'] ?? 'viewer';
        
        if ($isTargetAdmin && !$isOwner) {
            return new JsonResponse(['success' => false, 'error' => 'Only the project owner can modify admin permissions'], 403);
        }
        if ($newRole === 'admin' && !$isOwner) {
            return new JsonResponse(['success' => false, 'error' => 'Only the project owner can grant admin permissions'], 403);
        }
        if ($isTargetAdmin && $newRole !== 'admin' && !$isOwner) {
            return new JsonResponse(['success' => false, 'error' => 'Only the project owner can revoke admin permissions'], 403);
        }

        // Проверяем, был ли участник уже добавлен
        $existingMember = $this->entityManager->getRepository(ProjectMember::class)
            ->findOneBy(['project' => $project, 'user' => $user]);

        $isNewMember = ($existingMember === null);

        $member = $this->entityManager->getRepository(ProjectMember::class)
            ->findOneBy(['project' => $project, 'user' => $user]) ?? new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($data['role'] ?? 'viewer');

        // Очищаем все связи
        foreach ($member->getAreas() as $a) $member->removeArea($a);
        foreach ($member->getTasks() as $t) $member->removeTask($t);
        foreach ($member->getSubtasks() as $s) $member->removeSubtask($s);

        $selectedAreas = $data['areas'] ?? [];
        $selectedTasks = $data['tasks'] ?? [];

        // === НОВАЯ ЛОГИКА ДЛЯ EXECUTOR ===
        if ($member->getRole() === 'executor') {
            // Сначала обрабатываем области
            foreach ($project->getAreas() as $area) {
                $isAreaChecked = in_array($area->getId(), $selectedAreas);
                
                if ($isAreaChecked) {
                    // Если область выбрана - добавляем область и ВСЕ задачи в ней
                    $member->addArea($area);
                    foreach ($area->getTasks() as $task) {
                        $member->addTask($task);
                    }
                } else {
                    // Если область не выбрана - проверяем отдельные задачи
                    foreach ($area->getTasks() as $task) {
                        $isTaskChecked = in_array($task->getId(), $selectedTasks);
                        if ($isTaskChecked) {
                            $member->addTask($task);
                            // Также добавляем область, чтобы задача была видна
                            $member->addArea($area);
                        }
                    }
                }
            }
        } elseif ($member->getRole() === 'manager') {
            // Для менеджера только области
            foreach ($project->getAreas() as $area) {
                if (in_array($area->getId(), $selectedAreas)) {
                    $member->addArea($area);
                }
            }
        }

        $currentUser = $this->getUser();

        if ($isNewMember) {
            $message = $currentUser->getUserIdentifier() . " 👤 добавил(а) вас в проект «" . $project->getTitle() . "»";
            $targetUrl = $this->generateUrl('app_project_view', ['id' => $project->getId()]);
            
            $this->notificationService->sendNotificationToUser(
                $user,
                $project,
                $message,
                $targetUrl
            );
        }

        $this->entityManager->persist($member);
        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/project/{projectId}/member/remove/{userId}', name: 'api_project_member_remove', methods: ['POST'])]
    public function removeProjectMember(int $projectId, int $userId): JsonResponse
    {
        $currentUser = $this->getUser();
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        
        if (!$project) {
            return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);
        }
        
        // Проверка прав: только админ или владелец может удалять
        $isOwner = ($project->getOwner() === $currentUser);
        $isAdmin = false;
        
        if (!$isOwner) {
            $member = $this->entityManager->getRepository(ProjectMember::class)
                ->findOneBy(['project' => $project, 'user' => $currentUser]);
            $isAdmin = ($member && $member->getRole() === 'admin');
        }
        
        if (!$isOwner && !$isAdmin) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }
        
        // Нельзя удалить владельца проекта
        if ($project->getOwner()->getId() === $userId) {
            return new JsonResponse(['success' => false, 'error' => 'Cannot remove project owner'], 400);
        }
        
        $userToRemove = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$userToRemove) {
            return new JsonResponse(['success' => false, 'error' => 'User not found'], 404);
        }
        
        // Находим запись ProjectMember
        $memberToRemove = $this->entityManager->getRepository(ProjectMember::class)
            ->findOneBy(['project' => $project, 'user' => $userToRemove]);
        
        if (!$memberToRemove) {
            return new JsonResponse(['success' => false, 'error' => 'Member not found'], 404);
        }

        //Нельзя удалить админа (если только ты не владелец) ===
        if ($memberToRemove->getRole() === 'admin' && !$isOwner) {
            return new JsonResponse([
                'success' => false, 
                'error' => 'Cannot remove another admin. Only the project owner can remove admins.'
            ], 403);
        }
        
        // Отправляем уведомление удаленному пользователю (если он не текущий)
        if ($userToRemove !== $currentUser) {
            $message = $currentUser->getUserIdentifier() . " ❌ удалил(а) вас из проекта «" . $project->getTitle() . "»";
            $targetUrl = $this->generateUrl('app_dashboard'); // Ссылка на дашборд
            $notification = new Notification();
            $notification->setUser($userToRemove);
            $notification->setProject($project);
            $notification->setTitle($project->getTitle());
            $notification->setMessage($message);
            $notification->setTargetUrl($targetUrl);
            $this->entityManager->persist($notification);
        }
        
        // Удаляем участника
        $this->entityManager->remove($memberToRemove);
        $this->entityManager->flush();
        
        return new JsonResponse(['success' => true]);
    }

    #[Route('/comment/create', name: 'api_comment_create', methods: ['POST'])]
    public function createComment(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);

        $taskId = $request->request->get('task_id');
        $text = $request->request->get('text');
        $task = $this->entityManager->getRepository(Task::class)->find($taskId ?? 0);

        if (!$task) return new JsonResponse(['success' => false, 'error' => 'Task not found'], 404);

        $project = $task->getArea()->getProject();
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        if ($project->getOwner() !== $user && !$member) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (empty(trim($text)) && !$request->files->get('file')) {
            return new JsonResponse(['success' => false, 'error' => 'Comment cannot be empty'], 400);
        }

        $comment = new Comment();
        $comment->setText($text ?? '');
        $comment->setTask($task);
        $comment->setAuthor($user);

        $filePath = null;
        $fileName = null;
        
        // Обработка загрузки файла
        $file = $request->files->get('file');
        if ($file) {
            $uploadsDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/comments';
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = mb_strtolower($originalFilename);
            $safeFilename = preg_replace('/[^a-z0-9_]+/u', '-', $safeFilename);
            $safeFilename = trim($safeFilename, '-');
            if (empty($safeFilename)) {
                $safeFilename = 'attachment';
            }
            $finalFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
            try {
                $file->move($uploadsDirectory, $finalFilename);
                $filePath = '/uploads/comments/' . $finalFilename;
                $fileName = $file->getClientOriginalName();
                $comment->setFilePath($filePath);
                $comment->setFileName($fileName);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'error' => 'File upload failed'], 500);
            }
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        try {
            $this->notificationService->notifyNewComment($comment, $user);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            error_log('Notification error: ' . $e->getMessage());
        }

        // === ИСПРАВЛЕНО: Возвращаем JSON вместо редиректа ===
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'id' => $comment->getId(),
                'text' => $comment->getText(),
                'filePath' => $filePath,
                'fileName' => $fileName,
                'author' => $user->getEmail()
            ]);
        }

        return $this->redirectToRoute('app_project_view', ['id' => $project->getId()]);
    }

    #[Route('/comment/delete/{id}', name: 'api_comment_delete', methods: ['POST'])]
    public function deleteComment(int $id): JsonResponse
    {
        $user = $this->getUser();
        $comment = $this->entityManager->getRepository(Comment::class)->find($id);
        if (!$comment) return new JsonResponse(['success' => false, 'error' => 'Comment not found'], 404);

        $task = $comment->getTask();
        $area = $task->getArea();
        $project = $area->getProject();

        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        // ПРОВЕРКА ПРАВ ИЗ ТЗ: 
        // Удалить может: Автор комментария ИЛИ Админ ИЛИ Руководитель области, внутри которой задача
        $isAuthor = ($comment->getAuthor() === $user);
        $isAdmin = ($role === 'admin');
        $isAreaManager = ($role === 'manager' && $member && $member->getAreas()->contains($area));

        if (!$isAuthor && !$isAdmin && !$isAreaManager) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden. You cannot delete this comment.'], 403);
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/project/{id}/members-panel', name: 'api_project_members_panel', methods: ['GET'])]
    public function getMembersPanel(int $id): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) {
            return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);
        }
        
        // Убираем проверку прав на просмотр участников
        // Любой, кто имеет доступ к проекту, может видеть участников
        
        // Получаем всех участников, исключая владельца
        $allMembers = $this->entityManager->getRepository(ProjectMember::class)->findBy(['project' => $project]);
        error_log('Members found: ' . count($allMembers));
        
        $members = array_filter($allMembers, function($member) use ($project) {
            return $member->getUser()->getId() !== $project->getOwner()->getId();
        });
        
        // Сортируем по ролям
        $rolePriority = [
            'admin' => 1,
            'manager' => 2,
            'executor' => 3,
            'viewer' => 4
        ];
        
        usort($members, function($a, $b) use ($rolePriority) {
            return $rolePriority[$a->getRole()] <=> $rolePriority[$b->getRole()];
        });
        
        // Формируем данные для ответа
        $membersData = [];
        foreach ($members as $member) {
            $membersData[] = [
                'id' => $member->getId(),
                'userId' => $member->getUser()->getId(),
                'email' => $member->getUser()->getEmail(),
                'role' => $member->getRole(),
                'roleLabel' => $this->getRoleLabel($member->getRole()),
                'areasCount' => $member->getAreas()->count(),
                'tasksCount' => $member->getTasks()->count(),
                'isOwner' => false
            ];
        }
        
        // Добавляем владельца
        $ownerData = [
            'id' => null,
            'userId' => $project->getOwner()->getId(),
            'email' => $project->getOwner()->getEmail(),
            'role' => 'owner',
            'roleLabel' => '👑 Владелец',
            'areasCount' => 0,
            'tasksCount' => 0,
            'isOwner' => true
        ];
        
        array_unshift($membersData, $ownerData);
        
        return new JsonResponse([
            'success' => true,
            'members' => $membersData,
            'total' => count($membersData)
        ]);
    }

    private function getRoleLabel(string $role): string
    {
        return match($role) {
            'admin' => 'Админ',
            'manager' => 'Руководитель',
            'executor' => 'Исполнитель',
            'viewer' => 'Зритель',
            default => $role
        };
    }

}


