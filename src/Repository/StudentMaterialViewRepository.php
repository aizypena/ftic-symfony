<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\StudentMaterialView;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudentMaterialView>
 */
class StudentMaterialViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentMaterialView::class);
    }

    /**
     * @return int[]
     */
    public function findViewedMaterialIdsForStudentInCourse(User $student, Course $course): array
    {
        $rows = $this->createQueryBuilder('mv')
            ->select('IDENTITY(mv.material) AS materialId')
            ->join('mv.material', 'm')
            ->where('mv.student = :student')
            ->andWhere('m.course = :course')
            ->setParameter('student', $student)
            ->setParameter('course', $course)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(static fn (array $row) => (int) $row['materialId'], $rows));
    }
}
