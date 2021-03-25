<?php

namespace App\Repository;

use App\Entity\Story;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Story|null find($id, $lockMode = null, $lockVersion = null)
 * @method Story|null findOneBy(array $criteria, array $orderBy = null)
 * @method Story[]    findAll()
 * @method Story[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Story::class);
    }

    // /**
    //  * @return Story[] Returns an array of Story objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Story
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function getStories(User $user)
    {
        $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
        $dql = "SELECT s FROM App:Story s WHERE (s.user IN(SELECT IDENTITY(l.to_user) FROM App:LikeUser l WHERE IDENTITY(l.from_user) = :id) OR s.user = 1 OR s.user = :id) AND s.time_creation > :yesterday ORDER BY s.time_creation DESC";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('id', $user->getId())
            ->setParameter('yesterday', $yesterday);
        return $query->getResult();
    }


    public function getAllStories()
    {
        $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
        $dql = "SELECT s FROM App:Story s WHERE s.time_creation > :yesterday ORDER BY s.time_creation DESC";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('yesterday', $yesterday);
        return $query->getResult();
    }
}
