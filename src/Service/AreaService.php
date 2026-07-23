<?php

namespace App\Service;

use App\Entity\Area;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AreaService
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function createArea(User $user, int $projectId, string $title, string $description = ''): Area
    {
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw new NotFoundHttpException('Project not found');
        }

        if ($project->getOwner() !== $user) {
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            if (!$member || $member->getRole() !== 'admin') {
                throw new AccessDeniedHttpException('Forbidden');
            }
        }

        $area = new Area();
        $area->setTitle($title);
        $area->setDescription($description);
        $area->setProject($project);

        $this->entityManager->persist($area);
        $this->entityManager->flush();

        return $area;
    }

    public function getAreaTasksData(int $areaId): array
    {
        $area = $this->entityManager->getRepository(Area::class)->find($areaId);
        if (!$area) {
            throw new NotFoundHttpException('Area not found');
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
                'doneSubtasks' => array_reduce($task->getSubtasks()->toArray(), function ($carry, $sub) {
                    return $carry + ($sub->getStatus() === 'done' ? 1 : 0);
                }, 0)
            ];
        }

        return $tasks;
    }
}