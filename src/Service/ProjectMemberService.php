<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProjectMemberService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {}

    public function getProjectMembersList(int $projectId): array
    {
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw new NotFoundHttpException('Project not found');
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

        return $result;
    }

    public function saveProjectMember(User $currentUser, int $projectId, array $data): void
    {
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw new NotFoundHttpException('Project not found');
        }

        if (empty($data['user_id'])) {
            throw new BadRequestHttpException('Missing required parameter: user_id');
        }

        $user = $this->entityManager->getRepository(User::class)->find($data['user_id']);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        $targetMember = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);

        $isOwner = ($project->getOwner() === $currentUser);
        $isTargetAdmin = $targetMember && $targetMember->getRole() === 'admin';
        $newRole = $data['role'] ?? 'viewer';

        if ($isTargetAdmin && !$isOwner) {
            throw new AccessDeniedHttpException('Только владелец проекта может назначать админов');
        }
        if ($newRole === 'admin' && !$isOwner) {
            throw new AccessDeniedHttpException('Только владелец проекта может менять админов');
        }
        if ($isTargetAdmin && $newRole !== 'admin' && !$isOwner) {
            throw new AccessDeniedHttpException('Только владелец проекта может удалять админов');
        }

        $isNewMember = ($targetMember === null);

        $member = $targetMember ?? new ProjectMember();
        $member->setProject($project);
        $member->setUser($user);
        $member->setRole($newRole);

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
            $targetUrl = $this->urlGenerator->generate('app_project_view', ['id' => $project->getId()]);

            $this->notificationService->sendNotificationToUser(
                $user,
                $project,
                $message,
                $targetUrl
            );
        }

        $this->entityManager->persist($member);
        $this->entityManager->flush();
    }

    public function removeProjectMember(User $currentUser, int $projectId, int $userId): void
    {
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw new NotFoundHttpException('Project not found');
        }

        $isOwner = ($project->getOwner() === $currentUser);
        $isAdmin = false;

        if (!$isOwner) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $currentUser]);
            $isAdmin = ($member && $member->getRole() === 'admin');
        }

        if (!$isOwner && !$isAdmin) {
            throw new AccessDeniedHttpException('Forbidden');
        }

        if ($project->getOwner()->getId() === $userId) {
            throw new BadRequestHttpException('Нельзя удалить владельца');
        }

        $memberToRemove = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $userId]);
        if (!$memberToRemove) {
            throw new NotFoundHttpException('Участник не найден');
        }

        if ($memberToRemove->getRole() === 'admin' && !$isOwner) {
            throw new AccessDeniedHttpException('Нельзя удалить другого админа. Админа может удалить только владелец');
        }

        $userToRemove = $memberToRemove->getUser();
        if ($userToRemove !== $currentUser) {
            $message = $currentUser->getUserIdentifier() . " ❌ удалил(а) вас из проекта «" . $project->getTitle() . "»";
            $targetUrl = $this->urlGenerator->generate('app_dashboard');
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
    }

     public function getMembersPanelData(int $projectId): array
    {
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw new NotFoundHttpException('Project not found');
        }

        $members = $this->entityManager->getRepository(ProjectMember::class)->findMembersWithoutOwner($project);
        usort($members, [ProjectMember::class, 'compareByRole']);

        $membersData = [];
        foreach ($members as $member) {
            $user = $member->getUser();
            $profile = $user->getProfile();
            
            $membersData[] = [
                'id' => $member->getId(),
                'userId' => $user->getId(),
                'email' => $user->getEmail(),
                'fullName' => $profile ? $profile->getFullName() : null,
                'avatar' => $profile ? $profile->getAvatar() : null,
                'role' => $member->getRole(),
                'roleLabel' => $this->getRoleLabel($member->getRole()),
                'areasCount' => $member->getAreas()->count(),
                'tasksCount' => $member->getTasks()->count(),
                'isOwner' => false
            ];
        }

        $owner = $project->getOwner();
        $ownerProfile = $owner->getProfile();
        
        $ownerData = [
            'id' => null,
            'userId' => $owner->getId(),
            'email' => $owner->getEmail(),
            'fullName' => $ownerProfile ? $ownerProfile->getFullName() : null,
            'avatar' => $ownerProfile ? $ownerProfile->getAvatar() : null,
            'role' => 'owner',
            'roleLabel' => '👑 Владелец',
            'areasCount' => 0,
            'tasksCount' => 0,
            'isOwner' => true
        ];

        array_unshift($membersData, $ownerData);

        return $membersData;
    }


    private function getRoleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Админ',
            'manager' => 'Руководитель',
            'executor' => 'Исполнитель',
            'viewer' => 'Зритель',
            default => $role
        };
    }
}