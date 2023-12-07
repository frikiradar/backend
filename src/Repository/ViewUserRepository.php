<?php

namespace App\Repository;

use App\Entity\ViewUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ViewUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method ViewUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method ViewUser[]    findAll()
 * @method ViewUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ViewUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ViewUser::class);
    }

    // /**
    //  * @return ViewUser[] Returns an array of ViewUser objects
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
    public function findOneBySomeField($value): ?ViewUser
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function save(ViewUser $viewUser): void
    {
        $this->_em->persist($viewUser);
        $this->_em->flush();
    }

    public function remove(ViewUser $viewUser): void
    {
        $this->_em->remove($viewUser);
        $this->_em->flush();
    }
}
