<?php

namespace App\Entity;

use App\Repository\AcademicTermRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: AcademicTermRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AcademicTerm
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public const AVAILABLE_TERMS = ['Term 1', 'Term 2', 'Term 3'];

    #[ORM\Column(length: 20)]
    #[Assert\Regex(pattern: '/^\d{4}-\d{4}$/', message: 'Use the YYYY-YYYY format, e.g. 2025-2026.')]
    private string $schoolYear = '';

    #[ORM\Column(length: 50)]
    #[Assert\Choice(callback: [self::class, 'getAvailableTerms'], message: 'Select one of the supported terms.')]
    private string $termLabel = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isActive = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, Course> */
    #[ORM\OneToMany(mappedBy: 'term', targetEntity: Course::class)]
    private Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
    }

    public static function getAvailableTerms(): array
    {
        return self::AVAILABLE_TERMS;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchoolYear(): string
    {
        return $this->schoolYear;
    }

    public function setSchoolYear(string $schoolYear): static
    {
        $this->schoolYear = $schoolYear;

        return $this;
    }

    public function getSchoolYearBounds(): ?array
    {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $this->schoolYear, $matches)) {
            return null;
        }

        return [
            'start' => (int) $matches[1],
            'end'   => (int) $matches[2],
        ];
    }

    public function getTermLabel(): string
    {
        return $this->termLabel;
    }

    public function setTermLabel(?string $termLabel): static
    {
        $this->termLabel = $termLabel === null ? '' : $termLabel;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Course>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(Course $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setTerm($this);
        }

        return $this;
    }

    public function removeCourse(Course $course): static
    {
        $this->courses->removeElement($course);

        return $this;
    }

    public function containsDate(?\DateTimeImmutable $date): bool
    {
        if (!$this->startDate || !$this->endDate || !$date) {
            return false;
        }

        $target = $date->setTime(0, 0);

        return $target >= $this->startDate && $target <= $this->endDate;
    }

    public function getDisplayLabel(): string
    {
        $parts = array_filter([$this->schoolYear, $this->termLabel], fn($value) => $value !== '');
        return implode(' - ', $parts);
    }

    #[Assert\Callback]
    public function validateSchoolYearRange(ExecutionContextInterface $context): void
    {
        $bounds = $this->getSchoolYearBounds();

        if ($this->schoolYear === '' || $bounds === null) {
            return;
        }

        if ($bounds['end'] <= $bounds['start']) {
            $context
                ->buildViolation('The end year must be greater than the start year (e.g. 2025-2026).')
                ->atPath('schoolYear')
                ->addViolation();
        }
    }

    public function __toString(): string
    {
        return $this->getDisplayLabel();
    }
}
