<?php

namespace App\Repository;

use App\Entity\BlockUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method BlockUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method BlockUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method BlockUser[]    findAll()
 * @method BlockUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BlockUserRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, BlockUser::class);
    }

    // /**
    //  * @return BlockUser[] Returns an array of BlockUser objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?BlockUser
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
