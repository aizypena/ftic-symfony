<?php

namespace App\Repository;

use App\Entity\ProfessorEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfessorEvent>
 */
class ProfessorEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfessorEvent::class);
    }

    /**
     * @return ProfessorEvent[]
     */
    public function findForRange(User $professor, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.professor = :professor')
            ->andWhere('e.startsAt >= :start')
            ->andWhere('e.startsAt <= :end')
            ->setParameter('professor', $professor)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ProfessorEvent[]
     */
    public function findForStudentRange(User $student, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e')
            ->join('App\Entity\Course', 'c', 'WITH', 'c.professor = e.professor')
            ->join('c.students', 's')
            ->andWhere('s = :student')
            ->andWhere('e.startsAt >= :start')
            ->andWhere('e.startsAt <= :end')
            ->setParameter('student', $student)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
