<?php

namespace App\Repository;

use App\Entity\ViewAd;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ViewAd>
 *
 * @method ViewAd|null find($id, $lockMode = null, $lockVersion = null)
 * @method ViewAd|null findOneBy(array $criteria, array $orderBy = null)
 * @method ViewAd[]    findAll()
 * @method ViewAd[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ViewAdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ViewAd::class);
    }

//    /**
//     * @return ViewAd[] Returns an array of ViewAd objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('v.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ViewAd
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
