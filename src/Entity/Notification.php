<?php
// src/Entity/Notification.php
namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as MySql;

#[MySql\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[MySql\Id]
    #[MySql\GeneratedValue]
    #[MySql\Column]
    private ?int $id = null;

    #[MySql\ManyToOne(targetEntity: User::class)]
    #[MySql\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[MySql\ManyToOne(targetEntity: Project::class)]
    #[MySql\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[MySql\Column(length: 255)]
    private ?string $title = null; // Название проекта или заголовок

    #[MySql\Column(type: Types::TEXT)]
    private ?string $message = null; // "Конкретное изменение, которое произошло"

    #[MySql\Column(length: 255, nullable: true)]
    private ?string $targetUrl = null; // Ссылка для перехода (например, /dashboard/project/1#task-5)

    #[MySql\Column]
    private bool $isRead = false;

    #[MySql\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Геттеры и сеттеры
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): self { $this->project = $project; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }
    public function getTargetUrl(): ?string { return $this->targetUrl; }
    public function setTargetUrl(?string $targetUrl): self { $this->targetUrl = $targetUrl; return $this; }
    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $isRead): self { $this->isRead = $isRead; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }
}