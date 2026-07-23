<?php

namespace App\Entity;

use App\Repository\ProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProfileRepository::class)]
class Profile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'profile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $fullName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $position = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $university = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $specialty = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $educationLevel = null; // bachelor, master, phd

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $telegram = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $github = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $linkedin = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $website = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getFullName(): ?string { return $this->fullName; }
    public function setFullName(?string $fullName): self { $this->fullName = $fullName; return $this; }

    public function getCompany(): ?string { return $this->company; }
    public function setCompany(?string $company): self { $this->company = $company; return $this; }

    public function getPosition(): ?string { return $this->position; }
    public function setPosition(?string $position): self { $this->position = $position; return $this; }

    public function getUniversity(): ?string { return $this->university; }
    public function setUniversity(?string $university): self { $this->university = $university; return $this; }

    public function getSpecialty(): ?string { return $this->specialty; }
    public function setSpecialty(?string $specialty): self { $this->specialty = $specialty; return $this; }

    public function getEducationLevel(): ?string { return $this->educationLevel; }
    public function setEducationLevel(?string $educationLevel): self { 
        $this->educationLevel = $educationLevel; 
        return $this; 
    }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): self { $this->bio = $bio; return $this; }

    public function getAvatar(): ?string { return $this->avatar; }
    public function setAvatar(?string $avatar): self { $this->avatar = $avatar; return $this; }

    public function getTelegram(): ?string { return $this->telegram; }
    public function setTelegram(?string $telegram): self { $this->telegram = $telegram; return $this; }

    public function getGithub(): ?string { return $this->github; }
    public function setGithub(?string $github): self { $this->github = $github; return $this; }

    public function getLinkedin(): ?string { return $this->linkedin; }
    public function setLinkedin(?string $linkedin): self { $this->linkedin = $linkedin; return $this; }

    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): self { $this->website = $website; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getWorkInfo(): ?string
    {
        if ($this->company && $this->position) {
            return $this->position . ' в ' . $this->company;
        } elseif ($this->company) {
            return 'Работает в ' . $this->company;
        } elseif ($this->position) {
            return $this->position;
        }
        return null;
    }

    public function getEducationInfo(): ?string
    {
        if ($this->university && $this->specialty) {
            return $this->specialty . ' - ' . $this->university;
        } elseif ($this->university) {
            return 'Учится в ' . $this->university;
        } elseif ($this->specialty) {
            return $this->specialty;
        }
        return null;
    }

    public function getEducationLevelLabel(): ?string
    {
        return match($this->educationLevel) {
            'bachelor' => 'Бакалавр',
            'master' => 'Магистр',
            'phd' => 'PhD',
            default => null
        };
    }

    public function getTotalProjects(): int
    {
        if (!$this->user) return 0;
        return $this->user->getProjects()->count();
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}