<?php

namespace App\Entity;

use App\Repository\StudentMaterialViewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudentMaterialViewRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_student_material_view', columns: ['student_id', 'material_id'])]
class StudentMaterialView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $student;

    #[ORM\ManyToOne(targetEntity: CourseMaterial::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CourseMaterial $material;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $viewedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function setStudent(User $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getMaterial(): CourseMaterial
    {
        return $this->material;
    }

    public function setMaterial(CourseMaterial $material): static
    {
        $this->material = $material;

        return $this;
    }

    public function getViewedAt(): \DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function setViewedAt(\DateTimeImmutable $viewedAt): static
    {
        $this->viewedAt = $viewedAt;

        return $this;
    }
}
