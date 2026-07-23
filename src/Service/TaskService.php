<?php

namespace App\Service;

use App\Entity\Area;
use App\Entity\ProjectMember;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TaskService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {}

    public function createTask(User $user, int $areaId, string $title, ?string $description = null, ?string $deadlineStr = null): Task
    {
        $area = $this->entityManager->getRepository(Area::class)->find($areaId);
        if (!$area) {
            throw new NotFoundHttpException('Area not found');
        }

        $project = $area->getProject();
        if ($project->getOwner() !== $user) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            if (!$member || ($member->getRole() !== 'admin' && ($member->getRole() !== 'manager' || !$member->getAreas()->contains($area)))) {
                throw new AccessDeniedHttpException('Forbidden');
            }
        }

        $task = new Task();
        $task->setTitle($title);
        $task->setDescription($description);
        $task->setArea($area);
        $task->setStatus('todo');
        $task->setPriority('medium');

        if (!empty($deadlineStr)) {
            $task->setDeadline(new \DateTime($deadlineStr));
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        try {
            $this->notificationService->notifyNewTask($task, $user);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Notification error: ' . $e->getMessage());
        }

        return $task;
    }

    public function updateTask(User $user, int $taskId, string $field, mixed $value): void
    {
        $task = $this->entityManager->getRepository(Task::class)->find($taskId);
        if (!$task) {
            throw new NotFoundHttpException('Task not found');
        }

        $project = $task->getArea()->getProject();
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        if ($role === 'viewer') {
            throw new AccessDeniedHttpException('Forbidden');
        }
        if ($role === 'executor' && $field !== 'status' && $field !== 'description') {
            throw new AccessDeniedHttpException('Executors can only change status and description');
        }
        if ($role === 'manager' && !$member->getAreas()->contains($task->getArea())) {
            throw new AccessDeniedHttpException('Not your area');
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
    }

    public function getTaskSubtasksData(int $taskId): array
    {
        $task = $this->entityManager->getRepository(Task::class)->find($taskId);
        if (!$task) {
            throw new NotFoundHttpException('Task not found');
        }

        $subtasks = [];
        foreach ($task->getSubtasks() as $subtask) {
            $subtasks[] = [
                'id' => $subtask->getId(),
                'title' => $subtask->getTitle(),
                'status' => $subtask->getStatus()
            ];
        }

        return $subtasks;
    }
}