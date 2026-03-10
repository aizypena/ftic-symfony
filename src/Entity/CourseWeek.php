<?php

namespace App\Entity;

use App\Repository\CourseWeekRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseWeekRepository::class)]
class CourseWeek
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'weeks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Course $course;

    #[ORM\Column]
    private int $weekNumber = 1;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'week', targetEntity: CourseMaterial::class)]
    #[ORM\OrderBy(['originalName' => 'ASC'])]
    private Collection $materials;

    public function __construct()
    {
        $this->materials = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getCourse(): Course { return $this->course; }
    public function setCourse(Course $course): static { $this->course = $course; return $this; }

    public function getWeekNumber(): int { return $this->weekNumber; }
    public function setWeekNumber(int $weekNumber): static { $this->weekNumber = $weekNumber; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    /** @return Collection<int, CourseMaterial> */
    public function getMaterials(): Collection { return $this->materials; }
}
