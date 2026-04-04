<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    public function createEnrolledInActiveTermQueryBuilder(User $student): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->join('c.students', 's')
            ->join('c.term', 't')
            ->where('s = :student')
            ->andWhere('t.isActive = true')
            ->setParameter('student', $student);
    }

    public function findEnrolledInActiveTermCourseForStudent(User $student, int $courseId): ?Course
    {
        return $this->createEnrolledInActiveTermQueryBuilder($student)
            ->andWhere('c.id = :courseId')
            ->setParameter('courseId', $courseId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
