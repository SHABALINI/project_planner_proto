<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectMember>
 */
class ProjectMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectMember::class);
    }

    /**
     * Найти участника в проекте по пользователю
     */
    public function findMember(Project $project, User $user): ?ProjectMember
    {
        return $this->findOneBy([
            'project' => $project,
            'user' => $user
        ]);
    }

    /**
     * Получить всех участников проекта (кроме владельца)
     */
    public function findMembersExceptOwner(Project $project): array
    {
        return $this->createQueryBuilder('pm')
            ->where('pm.project = :project')
            ->andWhere('pm.user != :owner')
            ->setParameter('project', $project)
            ->setParameter('owner', $project->getOwner())
            ->orderBy('pm.role', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить участников с определенной ролью
     */
    public function findByRole(Project $project, string $role): array
    {
        return $this->findBy([
            'project' => $project,
            'role' => $role
        ]);
    }

    /**
     * Проверить, является ли пользователь участником проекта
     */
    public function isMember(Project $project, User $user): bool
    {
        return $this->findOneBy([
            'project' => $project,
            'user' => $user
        ]) !== null;
    }
}