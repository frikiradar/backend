<?php

namespace App\Repository;

use App\Entity\ClickAd;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClickAd>
 *
 * @method ClickAd|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClickAd|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClickAd[]    findAll()
 * @method ClickAd[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClickAdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClickAd::class);
    }

    //    /**
    //     * @return ClickAd[] Returns an array of ClickAd objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ClickAd
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function save(ClickAd $clickAd): void
    {
        $this->_em->persist($clickAd);
        $this->_em->flush();
    }

    public function remove(ClickAd $clickAd): void
    {
        $this->_em->remove($clickAd);
        $this->_em->flush();
    }
}
