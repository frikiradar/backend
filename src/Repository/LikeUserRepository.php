<?php

namespace App\Repository;

use App\Entity\LikeUser;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method LikeUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method LikeUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method LikeUser[]    findAll()
 * @method LikeUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LikeUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, LikeUser::class);
        $this->em = $entityManager;
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

    public function getLikeUsers(User $fromUser, $param, $page = null)
    {
        $dql = "SELECT IDENTITY(" . ($param == "delivered" ? "l.to_user)" : "l.from_user)") . " fromuser, l.date" . ($param == "delivered" ? " " : ", l.time_read ") .
            "FROM App:LikeUser l
            WHERE " . ($param == "delivered" ? "l.from_user" : "l.to_user") . " = :id ORDER BY l.id DESC";

        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('id', $fromUser->getId());
        if (!is_null($page)) {
            $limit = 30;
            $offset = ($page - 1) * $limit;
            $query->setFirstResult($offset)
                ->setMaxResults($limit);
        }
        $likes = $query->getResult();
        foreach ($likes as $key => $like) {
            $userId = $like["fromuser"];
            $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $userId));
            $blocked = $this->em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser) ? true : false;

            if ($toUser->getActive() && !$blocked) {
                $likes[$key]['user'] = [
                    'id' => $userId,
                    'username' => $toUser->getUsername(),
                    'name' => $toUser->getName(),
                    'description' => $toUser->getDescription(),
                    'avatar' =>  $toUser->getAvatar() ?: null,
                    'thumbnail' => $toUser->getThumbnail() ?: null
                ];
            } else {
                unset($likes[$key]);
            }
        }
        $likes = array_values($likes);

        return $likes;
    }

    public function countLikeUsers(User $fromUser, $param)
    {
        return $this->getEntityManager()
            ->createQuery("SELECT COUNT(l.id) FROM App:LikeUser l WHERE " . ($param == "delivered" ? "l.from_user" : "l.to_user") . " = :id")
            ->setParameter('id', $fromUser->getId())
            ->getSingleScalarResult();
    }
}
