<?php

namespace App\Repository;

use App\Entity\ViewStory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ViewStory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ViewStory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ViewStory[]    findAll()
 * @method ViewStory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ViewStoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ViewStory::class);
    }

    // /**
    //  * @return ViewStory[] Returns an array of ViewStory objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('v.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ViewStory
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
