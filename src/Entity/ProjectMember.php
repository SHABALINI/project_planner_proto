<?php

namespace App\Entity;

use App\Repository\ProjectMemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectMemberRepository::class)]
class ProjectMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $role = 'viewer'; // 'admin', 'manager', 'executor', 'viewer'

    #[ORM\ManyToMany(targetEntity: Area::class)]
    private Collection $areas;

    #[ORM\ManyToMany(targetEntity: Task::class)]
    private Collection $tasks;

    #[ORM\ManyToMany(targetEntity: Subtask::class)]
    private Collection $subtasks;

    public function __construct()
    {
        $this->areas = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->subtasks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): static { $this->project = $project; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getAreas(): Collection { return $this->areas; }
    public function addArea(Area $area): static { if (!$this->areas->contains($area)) $this->areas->add($area); return $this; }
    public function removeArea(Area $area): static { $this->areas->removeElement($area); return $this; }

    public function getTasks(): Collection { return $this->tasks; }
    public function addTask(Task $task): static { if (!$this->tasks->contains($task)) $this->tasks->add($task); return $this; }
    public function removeTask(Task $task): static { $this->tasks->removeElement($task); return $this; }

    public function getSubtasks(): Collection { return $this->subtasks; }
    public function addSubtask(Subtask $subtask): static { if (!$this->subtasks->contains($subtask)) $this->subtasks->add($subtask); return $this; }
    public function removeSubtask(Subtask $subtask): static { $this->subtasks->removeElement($subtask); return $this; }

    private const ROLE_PRIORITY = [
        'admin' => 1,
        'manager' => 2,
        'executor' => 3,
        'viewer' => 4,
    ];

    public static function compareByRole(self $a, self $b): int
    {
        $priorityA = self::ROLE_PRIORITY[$a->getRole()] ?? 99;
        $priorityB = self::ROLE_PRIORITY[$b->getRole()] ?? 99;

        return $priorityA <=> $priorityB;
    }
}