<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Radar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Radar|null find($id, $lockMode = null, $lockVersion = null)
 * @method Radar|null findOneBy(array $criteria, array $orderBy = null)
 * @method Radar[]    findAll()
 * @method Radar[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RadarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Radar::class);
    }

    // /**
    //  * @return Radar[] Returns an array of Radar objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Radar
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function isRadarNotified(User $fromUser, User $toUser)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.fromUser = :fromUser')
            ->andWhere('r.toUser = :toUser')
            ->andWhere('r.time_read IS NULL')
            ->setParameter('fromUser', $fromUser)
            ->setParameter('toUser', $toUser)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findById($fromId, $toId)
    {
        $dql = "SELECT r FROM App:Radar r WHERE r.fromUser = :from_id AND r.toUser = :to_id";
        $query = $this->getEntityManager()->createQuery($dql)
            ->setParameter('from_id', $fromId)
            ->setParameter('to_id', $toId);
        return $query->getOneOrNullResult();
    }
}
