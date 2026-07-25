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

    public function findMembersWithoutOwner(Project $project): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.project = :project')
            ->andWhere('m.user != :owner')
            ->setParameter('project', $project)
            ->setParameter('owner', $project->getOwner())
            ->getQuery()
            ->getResult();
    }

    public function isMember(Project $project, User $user): bool
    {
        return $this->findOneBy([
            'project' => $project,
            'user' => $user
        ]) !== null;
    }
}