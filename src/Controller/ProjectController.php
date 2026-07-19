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
use Psr\Log\LoggerInterface;


#[Route('/dashboard')]
class ProjectController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $entityManager, 
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ){}

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

        $allProjects = array_merge($ownedProjects, $joinedProjects);

        usort($allProjects, function($a, $b) {
            if ($a->isPinned() && !$b->isPinned()) {
                return -1;
            }
            if (!$a->isPinned() && $b->isPinned()) {
                return 1;
            }
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
        $memberInfo = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);

        if (!$isOwner && !$memberInfo) {
            throw $this->createNotFoundException('У вас нет доступа к этому проекту.');
        }

        $role = 'viewer';
        if ($isOwner) {
            $role = 'admin';
        } elseif ($memberInfo) {
            $role = $memberInfo->getRole();
        }

        $allowedAreas = $memberInfo ? array_map(fn($a) => $a->getId(), $memberInfo->getAreas()->toArray()) : [];
        $allowedTasks = $memberInfo ? array_map(fn($t) => $t->getId(), $memberInfo->getTasks()->toArray()) : [];

        $projectMembers = $this->entityManager->getRepository(ProjectMember::class)->findMembersWithoutOwner($project);

        usort($projectMembers, [ProjectMember::class, 'compareByRole']);

        return $this->render('project/project_view.html.twig', [
            'project' => $project,
            'userRole' => $role,
            'allowedAreas' => $allowedAreas,
            'allowedTasks' => $allowedTasks,
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
            $this->entityManager->flush(); 

            return new JsonResponse(['success' => true]);
            
        } catch (\Exception $e) {
            $this->logger->error('Project creation failed: ' . $e->getMessage());
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

        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) {
            return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);
        }

        $isOwner = ($project->getOwner() === $user);
        $memberInfo = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);

        if (!$isOwner && !$memberInfo) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

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
        if (empty($data['title']) || empty($data['project_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing required fields: project_id and title are mandatory.'], 400);
        }

        $project = $this->entityManager->getRepository(Project::class)->find($data['project_id']);

        if (!$project){
            return new JsonResponse(['success' => false, 'error' => 'Project not found'], 404);
        }
    
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

    #[Route('/task/create', name: 'api_task_create', methods: ['POST'])]
    public function createTask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        if (empty($data['title']) || empty($data['area_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing required fields: area_id and title are mandatory.'], 400);
        }
        $area = $this->entityManager->getRepository(Area::class)->find($data['area_id']);

        if (!$area) {
            return new JsonResponse(['success' => false, 'error' => 'Area not found'], 404);
        }

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
            $this->logger->error('Notification error: ' . $e->getMessage());
        }

        return new JsonResponse(['success' => true, 'id' => $task->getId()]);
    }

    #[Route('/subtask/create', name: 'api_subtask_create', methods: ['POST'])]
    public function createSubtask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        if(empty($data['title']) || empty($data['task_id'])){
            return new JsonResponse(['success'=> false,'message'=> 'Missing required fields: task_id and title are mandatory.']);
        }
        $task = $this->entityManager->getRepository(Task::class)->find($data['task_id']);

        if (!$task){
            return new JsonResponse(['success' => false, 'error' => 'Task not found'], 404);
        }

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
            $this->logger->error('Notification error: ' . $e->getMessage());
        }

        return new JsonResponse(['success' => true, 'id' => $subtask->getId()]);
    }

    #[Route('/task/update', name: 'api_task_update', methods: ['POST'])]
    public function updateTask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        if (empty($data) || !isset($data['field'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing required parameter: field'], 400); 
        }
        $task = $this->entityManager->getRepository(Task::class)->find($data['task_id']);
        if (!$task) return new JsonResponse(['success' => false], 404);

        $project = $task->getArea()->getProject();
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        $field = $data['field'];
        $value = $data['value'];

        if ($role === 'viewer') {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }
        if ($role === 'executor' && $field !== 'status' && $field !== 'description') {
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

        if ($changeText !== null) {
            try {
                $this->notificationService->notifyTaskChange($task, $user, $changeText);
            } catch (\Exception $e) {
                $this->logger->error('Notification error: ' . $e->getMessage());
            }
        }

        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    
    #[Route('/subtask/update', name: 'api_subtask_update', methods: ['POST'])]
    public function updateSubtask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        if (empty($data) || !isset($data['subtask_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing required parameter: subtask_id'], 400);
        }
        $subtask = $this->entityManager->getRepository(Subtask::class)->find($data['subtask_id']);
        if (!$subtask) return new JsonResponse(['success' => false], 404);

        $project = $subtask->getTask()->getArea()->getProject();
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        if ($role === 'viewer') return new JsonResponse(['success' => false], 403);
        if ($role === 'manager' && !$member->getAreas()->contains($subtask->getTask()->getArea())) return new JsonResponse(['success' => false], 403);

        $field = $data['field'] ?? 'status';
        $value = $data['value'] ?? 'todo';

        if ($field === 'description') {
            $subtask->setDescription($value ?: null);
        }else if ($field === 'status') {
            $oldStatus = $subtask->getStatus();
            $newStatus = $value;
            $subtask->setStatus($newStatus);

            if ($oldStatus !== $newStatus) {
                try {
                    $statusRu = ($newStatus === 'done' ? 'Выполнено' : 'Не выполнено');
                    $changeText = "→ статус «" . $statusRu . "» у подзадачи «" . $subtask->getTitle() . "»";
                    $this->notificationService->notifySubtaskChange($subtask, $user, $changeText);
                } catch (\Exception $e) {
                    $this->logger->error('Notification error: ' . $e->getMessage());
                }
            }

            return new JsonResponse(['success' => true]);
        }

        $this->entityManager->flush();
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

            $isOwner = ($project->getOwner() === $user);
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            $role = $isOwner ? 'admin' : ($member ? $member->getRole() : 'viewer');

            if ($role === 'viewer' || $role === 'executor') {
                return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
            }

            if ($role === 'manager') {
                $targetArea = null;
                if ($type === 'area') $targetArea = $entity;
                if ($type === 'task') $targetArea = $entity->getArea();
                if ($type === 'subtask') $targetArea = $entity->getTask()->getArea();

                if ($type !== 'project' && $targetArea && !$member->getAreas()->contains($targetArea)) {
                    return new JsonResponse(['success' => false, 'error' => 'Not your area'], 403);
                }
                if ($type === 'project') {
                    return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
                }
            }

            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
            
        } catch (\Exception $e) {
            $this->logger->error('Delete error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new JsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
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

        $this->logger->error('Returning ' . count($result) . ' users for members modal');
        
        return new JsonResponse($result);
    }

    #[Route('/project/{id}/member/save', name: 'api_project_member_save', methods: ['POST'])]
    public function saveProjectMember(int $id, Request $request): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) return new JsonResponse(['success' => false], 404);

        $currentUser = $this->getUser();
        $data = json_decode($request->getContent(), true);
        if (empty($data) || !isset($data['user_id'])) {
            return new JsonResponse(['success' => false, 'error' => 'Missing required parameter: user_id'], 400);
        }
        $user = $this->entityManager->getRepository(User::class)->find($data['user_id']);
        if (!$user) return new JsonResponse(['success' => false], 404);

        $targetMember = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        
        $isOwner = ($project->getOwner() === $currentUser);
        $isTargetAdmin = $targetMember && $targetMember->getRole() === 'admin';
        $newRole = $data['role'] ?? 'viewer';
        
        if ($isTargetAdmin && !$isOwner) {
            return new JsonResponse(['success' => false, 'error' => 'Только владелец проекта может назначать админов'], 403);
        }
        if ($newRole === 'admin' && !$isOwner) {
            return new JsonResponse(['success' => false, 'error' => 'Только владелец проекта может менять админов'], 403);
        }
        if ($isTargetAdmin && $newRole !== 'admin' && !$isOwner) {
            return new JsonResponse(['success' => false, 'error' => 'Только владелец проекта может удалять админов'], 403);
        }

        $isNewMember = ($targetMember === null);

        $member = $targetMember ?? new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($data['role'] ?? 'viewer');

        foreach ($member->getAreas() as $a) $member->removeArea($a);
        foreach ($member->getTasks() as $t) $member->removeTask($t);
        foreach ($member->getSubtasks() as $s) $member->removeSubtask($s);

        $selectedAreas = $data['areas'] ?? [];
        $selectedTasks = $data['tasks'] ?? [];

        if ($member->getRole() === 'executor') {
            foreach ($project->getAreas() as $area) {
                $isAreaChecked = in_array($area->getId(), $selectedAreas);
                
                if ($isAreaChecked) {
                    $member->addArea($area);
                    foreach ($area->getTasks() as $task) {
                        $member->addTask($task);
                    }
                } else {
                    foreach ($area->getTasks() as $task) {
                        $isTaskChecked = in_array($task->getId(), $selectedTasks);
                        if ($isTaskChecked) {
                            $member->addTask($task);
                            $member->addArea($area);
                        }
                    }
                }
            }
        } elseif ($member->getRole() === 'manager') {
            foreach ($project->getAreas() as $area) {
                if (in_array($area->getId(), $selectedAreas)) {
                    $member->addArea($area);
                }
            }
        }

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

        $isOwner = ($project->getOwner() === $currentUser);
        $isAdmin = false;
        
        if (!$isOwner) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $currentUser]);
            $isAdmin = ($member && $member->getRole() === 'admin');
        }
        
        if (!$isOwner && !$isAdmin) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if ($project->getOwner()->getId() === $userId) {
            return new JsonResponse(['success' => false, 'error' => 'Нельзя удалить владельца'], 400);
        }

        $memberToRemove = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $userId]);
        
        if (!$memberToRemove) {
            return new JsonResponse(['success' => false, 'error' => 'Участник не найден'], 404);
        }

        if ($memberToRemove->getRole() === 'admin' && !$isOwner) {
            return new JsonResponse([
                'success' => false, 
                'error' => 'Нельзя удалить другого админа. Админа может удалить только владелец'
            ], 403);
        }
 
        $userToRemove = $memberToRemove->getUser();
        if ($userToRemove !== $currentUser) {
            $message = $currentUser->getUserIdentifier() . " ❌ удалил(а) вас из проекта «" . $project->getTitle() . "»";
            $targetUrl = $this->generateUrl('app_dashboard'); 
            $notification = new Notification();
            $notification->setUser($userToRemove);
            $notification->setProject($project);
            $notification->setTitle($project->getTitle());
            $notification->setMessage($message);
            $notification->setTargetUrl($targetUrl);
            $this->entityManager->persist($notification);
        }

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
        $text = $request->request->get('text') ?? '';
        $task = $this->entityManager->getRepository(Task::class)->find($taskId ?? 0);

        if (!$task) return new JsonResponse(['success' => false, 'error' => 'Задача не найдена'], 404);

        $project = $task->getArea()->getProject();
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        if ($project->getOwner() !== $user && !$member) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (empty(trim($text)) && !$request->files->get('file')) {
            return new JsonResponse(['success' => false, 'error' => 'Комментарий не может быть пустым'], 400);
        }

        $comment = new Comment();
        $comment->setText($text ?? '');
        $comment->setTask($task);
        $comment->setAuthor($user);

        $filePath = null;
        $fileName = null;

        $file = $request->files->get('file');
        if ($file) {
            $mimeType = $file->getMimeType();
            $isImage = strpos($mimeType, 'image/') === 0;

            $comment->setIsImage($isImage);
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
                return new JsonResponse(['success' => false, 'error' => 'Ошибка загрузки файла'], 500);
            }
        }

        try {
            $this->notificationService->notifyNewComment($comment, $user);
        } catch (\Exception $e) {
            $this->logger->error('Notification error: ' . $e->getMessage());
        }

        if ($request->isXmlHttpRequest()) {
            $this->entityManager->persist($comment);
            $this->entityManager->flush();
            return new JsonResponse([
                'success' => true,
                'id' => $comment->getId(),
                'text' => $comment->getText(),
                'filePath' => $filePath,
                'fileName' => $fileName,
                'author' => $user->getEmail(),
                'isImage' => $comment->isImage() ?? false,
            ]);
        }

        return $this->redirectToRoute('app_project_view', ['id' => $project->getId()]);
    }

    #[Route('/comment/delete/{id}', name: 'api_comment_delete', methods: ['POST'])]
    public function deleteComment(int $id): JsonResponse
    {
        $user = $this->getUser();
        $comment = $this->entityManager->getRepository(Comment::class)->find($id);
        if (!$comment) return new JsonResponse(['success' => false, 'error' => 'Комментарий не найден'], 404);

        $task = $comment->getTask();
        $area = $task->getArea();
        $project = $area->getProject();

        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        $isAuthor = ($comment->getAuthor() === $user);
        $isAdmin = ($role === 'admin');
        $isAreaManager = ($role === 'manager' && $member && $member->getAreas()->contains($area));

        if (!$isAuthor && !$isAdmin && !$isAreaManager) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden. Вы не можете удалить этот комментарий'], 403);
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

        $members = $this->entityManager->getRepository(ProjectMember::class)->findMembersWithoutOwner($project);;
        
        usort($members, [ProjectMember::class, 'compareByRole']);

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
        
        return new JsonResponse(['success' => true, 'members' => $membersData, 'total' => count($membersData)]);
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


