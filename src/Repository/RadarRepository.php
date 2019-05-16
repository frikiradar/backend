<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Radar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Radar|null find($id, $lockMode = null, $lockVersion = null)
 * @method Radar|null findOneBy(array $criteria, array $orderBy = null)
 * @method Radar[]    findAll()
 * @method Radar[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RadarRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
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
            ->andWhere('r.timeRead IS NULL')
            ->setParameter('fromUser', $fromUser)
            ->setParameter('toUser', $toUser)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countUnread(User $toUser)
    {
        return $this->createQueryBuilder('r')
            ->select('count(r.id)')
            ->where('r.toUser = :toUser')
            ->andWhere('r.timeRead IS NULL')
            ->setParameter('toUser', $toUser->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
