<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\User;
use DateTimeImmutable;
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

    public function createActiveForStudentQueryBuilder(User $student, ?DateTimeImmutable $date = null): QueryBuilder
    {
        $date ??= new DateTimeImmutable('today');

        return $this->createQueryBuilder('c')
            ->join('c.students', 's')
            ->join('c.term', 't')
            ->where('s = :student')
            ->andWhere('c.status = :status')
            ->andWhere('t.isActive = true')
            ->andWhere(':today BETWEEN t.startDate AND t.endDate')
            ->setParameter('student', $student)
            ->setParameter('status', 'active')
            ->setParameter('today', $date->setTime(0, 0));
    }

    public function findActiveCourseForStudent(User $student, int $courseId, ?DateTimeImmutable $date = null): ?Course
    {
        return $this->createActiveForStudentQueryBuilder($student, $date)
            ->andWhere('c.id = :courseId')
            ->setParameter('courseId', $courseId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
