<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $trainer = null;

    #[ORM\ManyToOne(inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AcademicTerm $term = null;

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: CourseMaterial::class, cascade: ['persist', 'remove'])]
    private Collection $materials;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: CourseWeek::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['weekNumber' => 'ASC'])]
    private Collection $weeks;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'course_student')]
    private Collection $students;

    public function __construct()
    {
        $this->materials = new ArrayCollection();
        $this->weeks     = new ArrayCollection();
        $this->students  = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(?string $name): static
    {
        $this->name = $name === null ? '' : $name;

        return $this;
    }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getTrainer(): ?User { return $this->trainer; }
    public function setTrainer(?User $trainer): static { $this->trainer = $trainer; return $this; }

    public function getTerm(): ?AcademicTerm
    {
        return $this->term;
    }

    public function setTerm(?AcademicTerm $term): static
    {
        $this->term = $term;

        return $this;
    }

    public function isWithinActiveTerm(?\DateTimeImmutable $date = null): bool
    {
        if (!$this->term) {
            return false;
        }

        $date ??= new \DateTimeImmutable('today');

        return $this->term->isActive() && $this->term->containsDate($date);
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, CourseMaterial> */
    public function getMaterials(): Collection { return $this->materials; }

    /** @return Collection<int, CourseWeek> */
    public function getWeeks(): Collection { return $this->weeks; }

    /** @return Collection<int, User> */
    public function getStudents(): Collection { return $this->students; }

    public function addStudent(User $student): static
    {
        if (!$this->students->contains($student)) {
            $this->students->add($student);
        }
        return $this;
    }

    public function removeStudent(User $student): static
    {
        $this->students->removeElement($student);
        return $this;
    }

    public function hasStudent(User $student): bool
    {
        return $this->students->contains($student);
    }

    public function addWeek(CourseWeek $week): static
    {
        if (!$this->weeks->contains($week)) {
            $this->weeks->add($week);
            $week->setCourse($this);
        }
        return $this;
    }
}
