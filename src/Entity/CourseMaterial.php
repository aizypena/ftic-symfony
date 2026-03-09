<?php

namespace App\Entity;

use App\Repository\CourseMaterialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseMaterialRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CourseMaterial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'materials')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Course $course;

    /** Stored filename on disk (uuid-based) */
    #[ORM\Column(length: 255)]
    private string $filename = '';

    /** Original filename shown to users */
    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCourse(): Course { return $this->course; }
    public function setCourse(Course $course): static { $this->course = $course; return $this; }

    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }

    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $originalName): static { $this->originalName = $originalName; return $this; }

    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }
}
