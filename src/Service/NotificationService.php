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
        $members = $this->entityManager->getRepository(ProjectMember::class)->findBy(['project' => $project]);

        $usersToNotify = [];

        $owner = $project->getOwner();
        if ($owner && $owner !== $excludeUser) {
            $usersToNotify[$owner->getId()] = $owner;
        }

        foreach ($members as $member) {
            $user = $member->getUser();

            if (!$user || $user === $excludeUser) {
                continue;
            }

            $role = $member->getRole();

            if ($role === 'admin' || $role === 'viewer') {
                $usersToNotify[$user->getId()] = $user;
                continue;
            }

            if ($role === 'manager && $areaId') {
                foreach ($member->getAreas() as $area) {
                    if ($area->getId() === $areaId) {
                        $usersToNotify[$user->getId()] = $user;
                        break; 
                    }
                }
                continue;
            }

            if ($role === 'executor' && ($taskId || $subtaskId)) {
                $isAffected = false;

                if ($taskId) {
                    foreach ($member->getTasks() as $task) {
                        if ($task->getId() === $taskId) {
                            $isAffected = true;
                            break;
                        }
                    }
                }

                if ($subtaskId && !$isAffected) {
                    foreach ($member->getSubtasks() as $subtask) {
                        if ($subtask->getId() === $subtaskId) {
                            $isAffected = true;
                            break;
                        }
                    }
                }

                if ($isAffected) {
                    $usersToNotify[$user->getId()] = $user;
                }
            }
        }

        foreach ($usersToNotify as $user) {
            $this->createNotification(
                $user,
                $project,
                $message,
                $targetUrl
            );
        }
    }

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