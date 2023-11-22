<?php

namespace App\Repository;

use App\Entity\Story;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @method Story|null find($id, $lockMode = null, $lockVersion = null)
 * @method Story|null findOneBy(array $criteria, array $orderBy = null)
 * @method Story[]    findAll()
 * @method Story[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, AuthorizationCheckerInterface $security)
    {
        parent::__construct($registry, Story::class);
        $this->security = $security;
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
        $dql = "SELECT s FROM App:Story s WHERE (s.user IN(SELECT IDENTITY(l.to_user) FROM App:LikeUser l WHERE IDENTITY(l.from_user) = :id) OR s.user = 1 OR s.user = :id) AND s.time_creation > :yesterday ORDER BY s.time_creation ASC";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('id', $user->getId())
            ->setParameter('yesterday', $yesterday);
        return $query->getResult();
    }

    public function getStoriesBySlug(string $slug)
    {
        $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
        $dql = "SELECT s FROM App:Story s WHERE s.text LIKE :slug AND s.time_creation > :yesterday ORDER BY s.time_creation ASC";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('slug', '%' . $slug . '%')
            ->setParameter('yesterday', $yesterday);
        return $query->getResult();
    }

    public function getUserStories(User $user)
    {
        $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
        $dql = "SELECT s FROM App:Story s WHERE s.user = :id AND s.time_creation > :yesterday ORDER BY s.time_creation ASC";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('id', $user->getId())
            ->setParameter('yesterday', $yesterday);
        return $query->getResult();
    }


    public function getAllStories()
    {
        if (!$this->security->isGranted('ROLE_DEMO')) {
            $yesterday = date('Y-m-d', strtotime('-' . 1 . ' days', strtotime(date("Y-m-d"))));
            $dql = "SELECT s FROM App:Story s WHERE s.time_creation > :yesterday AND s.user NOT IN (SELECT u.id FROM App:User u WHERE u.banned = 1 OR u.roles LIKE '%ROLE_DEMO%') ORDER BY s.time_creation ASC";
            $query = $this->getEntityManager()
                ->createQuery($dql)
                ->setParameter('yesterday', $yesterday);
        } else {
            $dql = "SELECT s FROM App:Story s WHERE s.user IN (SELECT u.id FROM App:User u WHERE u.roles LIKE '%ROLE_DEMO%') ORDER BY s.time_creation ASC";
            $query = $this->getEntityManager()
                ->createQuery($dql);
        }

        return $query->getResult();
    }
}
