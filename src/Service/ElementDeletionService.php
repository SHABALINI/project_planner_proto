<?php

namespace App\Service;

use App\Entity\Area;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\Subtask;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ElementDeletionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function deleteElement(User $user, string $type, int $id): void
    {
        try {
            $entity = match ($type) {
                'project' => $this->entityManager->getRepository(Project::class)->find($id),
                'area'    => $this->entityManager->getRepository(Area::class)->find($id),
                'task'    => $this->entityManager->getRepository(Task::class)->find($id),
                'subtask' => $this->entityManager->getRepository(Subtask::class)->find($id),
                default   => null,
            };

            if (!$entity) {
                throw new NotFoundHttpException('Entity not found');
            }

            $project = match ($type) {
                'project' => $entity,
                'area'    => $entity->getProject(),
                'task'    => $entity->getArea()->getProject(),
                'subtask' => $entity->getTask()->getArea()->getProject(),
            };

            $isOwner = ($project->getOwner() === $user);
            $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
            $role = $isOwner ? 'admin' : ($member ? $member->getRole() : 'viewer');

            if ($role === 'viewer' || $role === 'executor') {
                throw new AccessDeniedHttpException('Forbidden');
            }

            if ($role === 'manager') {
                $targetArea = match ($type) {
                    'area'    => $entity,
                    'task'    => $entity->getArea(),
                    'subtask' => $entity->getTask()->getArea(),
                    default   => null
                };

                if ($type !== 'project' && $targetArea && !$member->getAreas()->contains($targetArea)) {
                    throw new AccessDeniedHttpException('Not your area');
                }
                if ($type === 'project') {
                    throw new AccessDeniedHttpException('Forbidden');
                }
            }

            if ($type === 'project') {
                if ($user->isProjectPinned($id)) {
                    $user->unpinProject($id);
                    $this->entityManager->persist($user);
                }
            }

            $this->entityManager->remove($entity);
            $this->entityManager->flush();

        } catch (\Exception $e) {
            $this->logger->error('Delete error: ' . $e->getMessage());
            throw $e;
        }
    }
}