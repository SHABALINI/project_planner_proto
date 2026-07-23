<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProjectService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function getDashboardProjects(User $user): array
    {
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

        usort($allProjects, function ($a, $b) use ($user) {
            $aPinned = $user->isProjectPinned($a->getId());
            $bPinned = $user->isProjectPinned($b->getId());

            if ($aPinned && !$bPinned) return -1;
            if (!$aPinned && $bPinned) return 1;
            return $b->getId() <=> $a->getId();
        });

        return $allProjects;
    }

    public function getProjectViewData(int $projectId, User $currentUser): array
    {
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw new NotFoundHttpException('Проект не найден.');
        }

        $allUsers = $this->entityManager->getRepository(User::class)->findAll();
        $isOwner = ($project->getOwner() === $currentUser);
        $memberInfo = $this->entityManager->getRepository(ProjectMember::class)->findOneBy([
            'project' => $project, 
            'user' => $currentUser
        ]);

        if (!$isOwner && !$memberInfo) {
            throw new AccessDeniedHttpException('У вас нет доступа к этому проекту.');
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

        return [
            'project' => $project,
            'userRole' => $role,
            'allowedAreas' => $allowedAreas,
            'allowedTasks' => $allowedTasks,
            'currentUser' => $currentUser,
            'allUsers' => $allUsers,
            'project_members' => $projectMembers,
        ];
    }

    public function createProject(User $user, string $title): Project
    {
        try {
            $project = new Project();
            $project->setTitle($title);
            $project->setOwner($user);

            $this->entityManager->persist($project);
            $this->entityManager->flush();

            return $project;
        } catch (\Exception $e) {
            $this->logger->error('Project creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function togglePin(int $projectId, User $user): bool
    {
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw new NotFoundHttpException('Project not found');
        }

        $isOwner = ($project->getOwner() === $user);
        $memberInfo = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);

        if (!$isOwner && !$memberInfo) {
            throw new AccessDeniedHttpException('Access denied');
        }

        $isPinned = $user->togglePinProject($project->getId());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $isPinned;
    }
}