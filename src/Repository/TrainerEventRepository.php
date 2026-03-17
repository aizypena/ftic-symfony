<?php

namespace App\Repository;

use App\Entity\TrainerEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainerEvent>
 */
class TrainerEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainerEvent::class);
    }

    /**
     * @return TrainerEvent[]
     */
    public function findForRange(User $trainer, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.trainer = :trainer')
            ->andWhere('e.startsAt >= :start')
            ->andWhere('e.startsAt <= :end')
            ->setParameter('trainer', $trainer)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}