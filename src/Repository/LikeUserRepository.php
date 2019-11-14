<?php

namespace App\Repository;

use App\Entity\LikeUser;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method LikeUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method LikeUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method LikeUser[]    findAll()
 * @method LikeUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LikeUserRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, LikeUser::class);
    }

    // /**
    //  * @return LikeUser[] Returns an array of LikeUser objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?LikeUser
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function getLikeUsers(User $user, $param)
    {
        $dql = "SELECT IDENTITY(l.from_user) fromuser, l.date, l.time_read
            FROM App:LikeUser l
            WHERE " . $param == "delivered" ? "l.to_user " : "l.from_user" . " = :id ORDER BY l.id DESC";

        $query = $this->getEntityManager()->createQuery($dql)->setParameter('id', $user->getId());
        return $query->getResult();
    }

    public function countUnread(User $toUser)
    {
        return $this->createQueryBuilder('l')
            ->select('count(l.id)')
            ->where('l.to_user = :toUser')
            ->andWhere('l.time_read IS NULL')
            ->setParameter('toUser', $toUser->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
