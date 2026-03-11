<?php

namespace App\Entity;

use App\Repository\AnnouncementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Announcement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $content = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $postedBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFilename = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getPostedBy(): User { return $this->postedBy; }
    public function setPostedBy(User $postedBy): static { $this->postedBy = $postedBy; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): static { $this->isDeleted = $isDeleted; return $this; }

    public function getImageFilename(): ?string { return $this->imageFilename; }
    public function setImageFilename(?string $imageFilename): static { $this->imageFilename = $imageFilename; return $this; }
}
