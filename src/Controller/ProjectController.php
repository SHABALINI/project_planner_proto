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

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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

        return $this->render('project/dashboard.html.twig', [
            'projects' => array_merge($ownedProjects, $joinedProjects),
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

        return $this->render('project/project_view.html.twig', [
            'project' => $project,
            'userRole' => $role,
            'allowedAreas' => $allowedAreas,
            'allowedTasks' => $allowedTasks,
            'allowedSubtasks' => $allowedSubtasks,
            'currentUser' => $user,
            'allUsers' => $allUsers,
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
        
        // ПРОВЕРКА ПРАВ: Админ ИЛИ Руководитель этой области
        if ($project->getOwner() !== $user) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            if (!$member || ($member->getRole() !== 'admin' && ($member->getRole() !== 'manager' || !$member->getAreas()->contains($area)))) {
                return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
            }
        }

        $task = new Task();
        $task->setTitle($data['title']);
        $task->setArea($area);
        $task->setStatus('todo');
        $task->setPriority('medium');

        if (!empty($data['deadline'])) {
            $task->setDeadline(new \DateTime($data['deadline']));
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/subtask/create', name: 'api_subtask_create', methods: ['POST'])]
    public function createSubtask(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $task = $this->entityManager->getRepository(Task::class)->find($data['task_id'] ?? 0);
        if (!$task) return new JsonResponse(['success' => false, 'error' => 'Task not found'], 404);

        $project = $task->getArea()->getProject();

        // ПРОВЕРКА ПРАВ: Админ ИЛИ Руководитель области
        if ($project->getOwner() !== $user) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            if (!$member || ($member->getRole() !== 'admin' && ($member->getRole() !== 'manager' || !$member->getAreas()->contains($task->getArea())))) {
                return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
            }
        }

        $subtask = new Subtask();
        $subtask->setTitle($data['title']);
        $subtask->setStatus('todo');
        $subtask->setTask($task);

        $this->entityManager->persist($subtask);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
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

        // Защита изменения параметров (Зритель не может ничего, Исполнитель может ТОЛЬКО статус)
        if ($role === 'viewer') {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }
        if ($role === 'executor' && $field !== 'status') {
            return new JsonResponse(['success' => false, 'error' => 'Executors can only change status'], 403);
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
        } elseif ($field === 'description') {
            $task->setDescription($value);
            $changeText = "обновил(а) описание задачи «" . $task->getTitle() . "»";
        } elseif ($field === 'deadline') {
            // Дедлайн приходит строкой "YYYY-MM-DD" или пустой. Превращаем в DateTime или null
            if (!empty($value)) {
                $task->setDeadline(new \DateTime($value));
                $changeText = "изменил(а) дедлайн задачи «" . $task->getTitle() . "» на " . date('d.m.Y', strtotime($value));
            } else {
                $task->setDeadline(null);
                $changeText = "удалил(а) дедлайн у задачи «" . $task->getTitle() . "»";
            }
        }

        // ОТПРАВКА УВЕДОМЛЕНИЯ (Выполняется, только если произошло известное нам изменение)
        if ($changeText !== null) {
            $project = $task->getArea()->getProject();
            $currentUser = $this->getUser();
            $owner = $project->getOwner();

            $notification = new \App\Entity\Notification();

            
            if ($owner && $owner !== $currentUser) {
                $notification = new \App\Entity\Notification();
                $notification->setUser($owner);
                $notification->setProject($project);
                $notification->setTitle($project->getTitle());
                $notification->setMessage("Пользователь " . $currentUser->getUserIdentifier() . " " . $changeText);
                $notification->setTargetUrl($this->generateUrl('app_project_view', ['id' => $project->getId()]) . '#task-node-' . $task->getId());
                
                // Используем $this->entityManager, так как свойство объявлено в конструкторе твоего класса
                $this->entityManager->persist($notification);
            }
            $notification->setMessage($currentUser->getUserIdentifier() . ": " . $changeText);
        }

        $project = $task->getArea()->getProject();
        $currentUser = $this->getUser();

        $owner = $project->getOwner();
        if ($owner && $owner !== $currentUser) {
            $notification = new \App\Entity\Notification();
            $notification->setUser($owner);
            $notification->setProject($project);
            $notification->setTitle($project->getTitle());
            $notification->setMessage("Пользователь " . $currentUser->getUserIdentifier() . " " . $changeText);
            
            // Генерируем ссылку, чтобы кнопка «Перейти» вела сразу на эту задачу
            $notification->setTargetUrl($this->generateUrl('app_project_view', ['id' => $project->getId()]) . '#task-node-' . $task->getId());
            
            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();
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

        $subtask->setStatus($data['status']);
        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/delete/{type}/{id}', name: 'api_element_delete', methods: ['POST'])]
    public function deleteElement(string $type, int $id): JsonResponse
    {
        $user = $this->getUser();
        $entity = null;
        if ($type === 'project') $entity = $this->entityManager->getRepository(Project::class)->find($id);
        if ($type === 'area') $entity = $this->entityManager->getRepository(Area::class)->find($id);
        if ($type === 'task') $entity = $this->entityManager->getRepository(Task::class)->find($id);
        if ($type === 'subtask') $entity = $this->entityManager->getRepository(Subtask::class)->find($id);

        if (!$entity) return new JsonResponse(['success' => false], 404);

        // Ищем проект для проверки роли удаления
        $project = $type === 'project' ? $entity : ($type === 'area' ? $entity->getProject() : $entity->getTask()->getArea()->getProject());
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        // Удалять структуры могут только админы, а руководители — только внутри своих областей
        if ($role === 'viewer' || $role === 'executor') return new JsonResponse(['success' => false], 403);
        if ($role === 'manager' && $type === 'area' && !$member->getAreas()->contains($entity)) return new JsonResponse(['success' => false], 403);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/project/{id}/members', name: 'api_project_members', methods: ['GET'])]
    public function getProjectMembers(int $id): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) return new JsonResponse(['success' => false], 404);

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
            if ($project->getOwner() === $u) continue;
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

    #[Route('/project/{id}/member/save', name: 'api_project_member_save', methods: ['POST'])]
    public function saveProjectMember(int $id, Request $request): JsonResponse
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        if (!$project) return new JsonResponse(['success' => false], 404);

        $data = json_decode($request->getContent(), true);
        $user = $this->entityManager->getRepository(User::class)->find($data['user_id'] ?? 0);
        if (!$user) return new JsonResponse(['success' => false], 404);

        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]) ?? new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($data['role'] ?? 'viewer');

        foreach ($member->getAreas() as $a) $member->removeArea($a);
        foreach ($member->getTasks() as $t) $member->removeTask($t);
        foreach ($member->getSubtasks() as $s) $member->removeSubtask($s);

        $selectedAreas = $data['areas'] ?? [];
        $selectedTasks = $data['tasks'] ?? [];
        $selectedSubtasks = $data['subtasks'] ?? [];

        foreach ($project->getAreas() as $area) {
            $isAreaChecked = in_array($area->getId(), $selectedAreas);
            if ($isAreaChecked && $member->getRole() === 'manager') $member->addArea($area);

            foreach ($area->getTasks() as $task) {
                $isTaskChecked = in_array($task->getId(), $selectedTasks);
                if (($isAreaChecked || $isTaskChecked) && $member->getRole() === 'executor') {
                    $member->addTask($task);
                    $member->addArea($area);
                }

                foreach ($task->getSubtasks() as $subtask) {
                    $isSubtaskChecked = in_array($subtask->getId(), $selectedSubtasks);
                    if (($isAreaChecked || $isTaskChecked || $isSubtaskChecked) && $member->getRole() === 'executor') {
                        $member->addSubtask($subtask);
                    }
                }
            }
        }

        $this->entityManager->persist($member);
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

        // Проверка: Зрители могут писать комментарии, но у пользователя должен быть хоть какой-то доступ к проекту
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

        // Обработка загрузки файла
        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        if ($file) {
            $uploadsDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/comments';
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            
            // ИСПРАВЛЕНО: Безопасная очистка имени файла без использования transliterator_transliterate
            // Переводим в нижний регистр и заменяем всё, кроме латиницы, цифр и подчёркивания, на дефисы
            $safeFilename = mb_strtolower($originalFilename);
            $safeFilename = preg_replace('/[^a-z0-9_]+/u', '-', $safeFilename);
            $safeFilename = trim($safeFilename, '-');
            
            // Если после очистки имя стало пустым (например, файл назывался только на кириллице), даём дефолтное имя
            if (empty($safeFilename)) {
                $safeFilename = 'attachment';
            }

            $finalFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

            try {
                $file->move($uploadsDirectory, $finalFilename);
                $comment->setFilePath('/uploads/comments/' . $finalFilename);
                $comment->setFileName($file->getClientOriginalName());
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'error' => 'File upload failed'], 500);
            }
        }

        // ПОДГОТОВКА ТЕКСТА ДЛЯ УВЕДОМЛЕНИЯ
        // Получаем текст из запроса, если его нет — ставим пустую строку
        $submittedText = $request->request->get('text', ''); 

        $project = $task->getArea()->getProject();
        $currentUser = $this->getUser();
        $owner = $project->getOwner();

        // Отправляем уведомление владельцу проекта (если комментарий написал не он сам)
        if ($owner && $owner !== $currentUser) {
            $notification = new \App\Entity\Notification();
            $notification->setUser($owner);
            $notification->setProject($project);
            $notification->setTitle($project->getTitle());
            
            // Используем $submittedText вместо неопределенной $commentText
            $shortEmail = $currentUser->getUserIdentifier();
            $shortText = mb_strimwidth($submittedText, 0, 40, "...");

            $notification->setMessage($shortEmail . " 💬 оставил(а) комментарий к задаче «" . $task->getTitle() . "»");
            
            // Ссылка-якорь прямо на карточку задачи
            $notification->setTargetUrl($this->generateUrl('app_project_view', ['id' => $project->getId()]) . '#task-node-' . $task->getId());
            
            $this->entityManager->persist($notification);
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

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
}