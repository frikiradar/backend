<?php

namespace App\Repository;

use App\Entity\MatchRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchRate>
 *
 * @method MatchRate|null find($id, $lockMode = null, $lockVersion = null)
 * @method MatchRate|null findOneBy(array $criteria, array $orderBy = null)
 * @method MatchRate[]    findAll()
 * @method MatchRate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MatchRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchRate::class);
    }

//    /**
//     * @return MatchRate[] Returns an array of MatchRate objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?MatchRate
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
