<?php

namespace App\Repository;

use App\Entity\AcademicTerm;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AcademicTerm>
 */
class AcademicTermRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AcademicTerm::class);
    }

    public function findCurrentTerm(?DateTimeImmutable $date = null): ?AcademicTerm
    {
        $targetDate = ($date ?? new DateTimeImmutable('today'))->setTime(0, 0);

        return $this->createQueryBuilder('t')
            ->where('t.isActive = true')
            ->andWhere(':today BETWEEN t.startDate AND t.endDate')
            ->setParameter('today', $targetDate)
            ->orderBy('t.startDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deactivateAllExcept(?AcademicTerm $term = null): void
    {
        $qb = $this->createQueryBuilder('t')
            ->update(AcademicTerm::class, 't')
            ->set('t.isActive', ':inactive')
            ->setParameter('inactive', false);

        if ($term && $term->getId()) {
            $qb->andWhere('t.id != :termId')
               ->setParameter('termId', $term->getId());
        }

        $qb->getQuery()->execute();
    }
}
