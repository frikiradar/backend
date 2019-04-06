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

    public function getToLikes(User $user)
    {
        $latitude = $user->getCoordinates()->getLatitude();
        $longitude = $user->getCoordinates()->getLongitude();

        $dql = "SELECT u.id,
            u.username,
            u.description,
            (DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age,
            u.location,
            u.hide_location,
            u.block_messages,
            (GLength(
                    LineStringFromWKB(
                        LineString(
                            u.coordinates,
                            GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                        )
                    )
                ) * 100) distance
            FROM App:User u WHERE u.id IN
            (SELECT IDENTITY(l.from_user) FROM App:LikeUser l WHERE l.to_user = :toUser)";
        $query = $this->getEntityManager()->createQuery($dql)->setParameter("toUser", $user);

        return $query->getResult();
    }
}
