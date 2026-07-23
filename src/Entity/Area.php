<?php

namespace App\Entity;

use App\Repository\AreaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as MyMapping;

#[MyMapping\Entity(repositoryClass: AreaRepository::class)]
class Area
{
    #[MyMapping\Id]
    #[MyMapping\GeneratedValue]
    #[MyMapping\Column]
    private ?int $id = null;

    #[MyMapping\Column(length: 255)]
    private ?string $title = null;

    #[MyMapping\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[MyMapping\ManyToOne(inversedBy: 'areas')]
    #[MyMapping\JoinColumn(nullable: false)]
    private ?Project $project = null;

    // ИСПРАВЛЕНО: Явно задаем инициализацию коллекции прямо при объявлении свойства
    #[MyMapping\OneToMany(targetEntity: Task::class, mappedBy: 'area', orphanRemoval: true)]
    private Collection $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setArea($this);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            // set the owning side to null (unless already changed)
            if ($task->getArea() === $this) {
                $task->setArea(null);
            }
        }

        return $this;
    }
}