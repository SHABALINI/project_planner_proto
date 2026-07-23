<?php

namespace App\Service;

use App\Entity\ProjectMember;
use App\Entity\Subtask;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SubtaskService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {}

    public function createSubtask(User $user, int $taskId, string $title, ?string $description = null): Subtask
    {
        $task = $this->entityManager->getRepository(Task::class)->find($taskId);
        if (!$task) {
            throw new NotFoundHttpException('Task not found');
        }

        $project = $task->getArea()->getProject();
        if ($project->getOwner() !== $user) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            if (!$member || ($member->getRole() !== 'admin' && ($member->getRole() !== 'manager' || !$member->getAreas()->contains($task->getArea())))) {
                throw new AccessDeniedHttpException('Forbidden');
            }
        }

        $subtask = new Subtask();
        $subtask->setTitle($title);
        $subtask->setDescription($description);
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

        return $subtask;
    }

    public function updateSubtask(User $user, int $subtaskId, string $field, string $value): bool
    {
        $subtask = $this->entityManager->getRepository(Subtask::class)->find($subtaskId);
        if (!$subtask) {
            throw new NotFoundHttpException('Subtask not found');
        }

        $project = $subtask->getTask()->getArea()->getProject();
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        if ($role === 'viewer') {
            throw new AccessDeniedHttpException('Forbidden');
        }
        if ($role === 'manager' && !$member->getAreas()->contains($subtask->getTask()->getArea())) {
            throw new AccessDeniedHttpException('Forbidden');
        }

        if ($field === 'description') {
            $subtask->setDescription($value ?: null);
            $this->entityManager->flush();
            return false; // Показываем, что обновления статуса не было
        } elseif ($field === 'status') {
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

            $this->entityManager->flush();
            return true;
        }

        throw new \InvalidArgumentException('Unknown field');
    }
}