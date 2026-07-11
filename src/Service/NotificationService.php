<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\ProjectMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
    }


    public function sendNotificationToUser(
        User $user,
        Project $project,
        string $message,
        string $targetUrl
    ): void {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setProject($project);
        $notification->setTitle($project->getTitle());
        $notification->setMessage($message);
        $notification->setTargetUrl($targetUrl);
        $notification->setIsRead(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    /**
     * Отправляет уведомление всем участникам, которых касается изменение
     */
    public function sendNotification(
        Project $project,
        string $message,
        string $targetUrl,
        ?User $excludeUser = null,
        ?string $areaId = null,
        ?string $taskId = null,
        ?string $subtaskId = null,
        string $type = 'general'
    ): void {
        // Получаем всех участников проекта
        $members = $this->entityManager->getRepository(ProjectMember::class)
            ->findBy(['project' => $project]);

        $usersToNotify = [];

        // 1. Владелец проекта (всегда получает уведомления)
        $owner = $project->getOwner();
        if ($owner && $owner !== $excludeUser) {
            $usersToNotify[$owner->getId()] = $owner;
        }

        // 2. Администраторы (всегда получают уведомления)
        foreach ($members as $member) {
            if ($member->getRole() === 'admin') {
                $user = $member->getUser();
                if ($user && $user !== $excludeUser) {
                    $usersToNotify[$user->getId()] = $user;
                }
            }
        }

        // 3. Зрители (получают все уведомления)
        foreach ($members as $member) {
            if ($member->getRole() === 'viewer') {
                $user = $member->getUser();
                if ($user && $user !== $excludeUser) {
                    $usersToNotify[$user->getId()] = $user;
                }
            }
        }

        // 4. Руководители (получают уведомления только по своим областям)
        if ($areaId) {
            foreach ($members as $member) {
                if ($member->getRole() === 'manager') {
                    // Проверяем, привязан ли руководитель к этой области
                    $isManagerOfArea = false;
                    foreach ($member->getAreas() as $area) {
                        if ($area->getId() == $areaId) {
                            $isManagerOfArea = true;
                            break;
                        }
                    }
                    
                    if ($isManagerOfArea) {
                        $user = $member->getUser();
                        if ($user && $user !== $excludeUser) {
                            $usersToNotify[$user->getId()] = $user;
                        }
                    }
                }
            }
        }

        // 5. Исполнители (получают уведомления только по своим задачам/подзадачам)
        if ($taskId || $subtaskId) {
            foreach ($members as $member) {
                if ($member->getRole() === 'executor') {
                    $isAffected = false;
                    
                    // Проверяем задачи
                    if ($taskId) {
                        foreach ($member->getTasks() as $task) {
                            if ($task->getId() == $taskId) {
                                $isAffected = true;
                                break;
                            }
                        }
                    }
                    
                    // Проверяем подзадачи
                    if ($subtaskId && !$isAffected) {
                        foreach ($member->getSubtasks() as $subtask) {
                            if ($subtask->getId() == $subtaskId) {
                                $isAffected = true;
                                break;
                            }
                        }
                    }
                    
                    if ($isAffected) {
                        $user = $member->getUser();
                        if ($user && $user !== $excludeUser) {
                            $usersToNotify[$user->getId()] = $user;
                        }
                    }
                }
            }
        }

        error_log(sprintf(
            'Sending notification to %d users for project "%s"',
            count($usersToNotify),
            $project->getTitle()
        ));

        // Отправляем уведомления
        foreach ($usersToNotify as $user) {
            $this->createNotification(
                $user,
                $project,
                $message,
                $targetUrl
            );
        }
    }

    /**
     * Создает одно уведомление для пользователя
     */
    private function createNotification(
        User $user,
        Project $project,
        string $message,
        string $targetUrl
    ): void {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setProject($project);
        $notification->setTitle($project->getTitle());
        $notification->setMessage($message);
        $notification->setTargetUrl($targetUrl);
        $notification->setIsRead(false);

        $this->entityManager->persist($notification);
    }

    /**
     * Вспомогательный метод для отправки уведомлений о изменении задачи
     */
    public function notifyTaskChange(
        $task,
        User $currentUser,
        string $changeText
    ): void {
        $project = $task->getArea()->getProject();
        $areaId = $task->getArea()->getId();
        $taskId = $task->getId();
        
        $message = $currentUser->getUserIdentifier() . ": " . $changeText;
        $targetUrl = $this->urlGenerator->generate('app_project_view', [
            'id' => $project->getId()
        ]) . '#task-node-' . $taskId;

        $this->sendNotification(
            $project,
            $message,
            $targetUrl,
            $currentUser,
            $areaId,
            $taskId,
            null
        );
    }

    /**
     * Вспомогательный метод для отправки уведомлений о изменении подзадачи
     */
    public function notifySubtaskChange(
        $subtask,
        User $currentUser,
        string $changeText
    ): void {
        $task = $subtask->getTask();
        $project = $task->getArea()->getProject();
        $areaId = $task->getArea()->getId();
        $taskId = $task->getId();
        $subtaskId = $subtask->getId();
        
        $message = $currentUser->getUserIdentifier() . ": " . $changeText;
        $targetUrl = $this->urlGenerator->generate('app_project_view', [
            'id' => $project->getId()
        ]) . '#task-node-' . $taskId;

        $this->sendNotification(
            $project,
            $message,
            $targetUrl,
            $currentUser,
            $areaId,
            $taskId,
            $subtaskId
        );
    }

    /**
     * Вспомогательный метод для отправки уведомлений о новом комментарии
     */
    public function notifyNewComment(
        $comment,
        User $currentUser
    ): void {
        $task = $comment->getTask();
        $project = $task->getArea()->getProject();
        $areaId = $task->getArea()->getId();
        $taskId = $task->getId();
        
        $shortText = mb_strimwidth($comment->getText() ?? '', 0, 40, "...");
        $message = $currentUser->getUserIdentifier() . " 💬 оставил(а) комментарий к задаче «" . $task->getTitle() . "»";
        $targetUrl = $this->urlGenerator->generate('app_project_view', [
            'id' => $project->getId()
        ]) . '#task-node-' . $taskId;

        $this->sendNotification(
            $project,
            $message,
            $targetUrl,
            $currentUser,
            $areaId,
            $taskId,
            null
        );
    }

    /**
     * Вспомогательный метод для отправки уведомлений о добавлении новой задачи
     */
    public function notifyNewTask(
        $task,
        User $currentUser
    ): void {
        $project = $task->getArea()->getProject();
        $areaId = $task->getArea()->getId();
        $taskId = $task->getId();
        
        $message = $currentUser->getUserIdentifier() . " ➕ создал(а) новую задачу «" . $task->getTitle() . "»";
        $targetUrl = $this->urlGenerator->generate('app_project_view', [
            'id' => $project->getId()
        ]) . '#task-node-' . $taskId;

        $this->sendNotification(
            $project,
            $message,
            $targetUrl,
            $currentUser,
            $areaId,
            $taskId,
            null
        );
    }

    /**
     * Вспомогательный метод для отправки уведомлений о добавлении новой подзадачи
     */
    public function notifyNewSubtask(
        $subtask,
        User $currentUser
    ): void {
        $task = $subtask->getTask();
        $project = $task->getArea()->getProject();
        $areaId = $task->getArea()->getId();
        $taskId = $task->getId();
        $subtaskId = $subtask->getId();
        
        $message = $currentUser->getUserIdentifier() . " ➕ создал(а) новую подзадачу «" . $subtask->getTitle() . "» в задаче «" . $task->getTitle() . "»";
        $targetUrl = $this->urlGenerator->generate('app_project_view', [
            'id' => $project->getId()
        ]) . '#task-node-' . $taskId;

        $this->sendNotification(
            $project,
            $message,
            $targetUrl,
            $currentUser,
            $areaId,
            $taskId,
            $subtaskId
        );
    }
}