<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

use CrEOF\Spatial\Tests\Fixtures\LineStringEntity;
use CrEOF\Spatial\PHP\Types\Geometry\LineString;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use CrEOF\Spatial\Tests\OrmTestCase;
use Doctrine\ORM\Query;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, User::class);
    }

    // /**
    //  * @return User[] Returns an array of User objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
     */

    /*
    public function findOneBySomeField($value): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
     */

    public function findeOneUser(int $id)
    {
        $em = $this->getEntityManager();
        $dql = 'SELECT u FROM App:User u  WHERE u.id=:id';
        $query = $em->createQuery($dql)->setParameters(['id' => $id]);

        /* @var \AppBundle\Entity\User $user */
        $user = $query->getOneOrNullResult();
        return $user;
    }

    public function getUsersByDistance(User $user, int $ratio)
    {
        $em = $this->getEntityManager();

        $latitude = $user->getCoordinates()->getLatitude();
        $longitude = $user->getCoordinates()->getLongitude();

        return $this->createQueryBuilder('u')
            ->select(array(
                'u.id',
                'u.username',
                'u.description',
                '(DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age',
                'u.gender',
                'u.orientation',
                'u.pronoun',
                'u.relationship',
                'u.status',
                'u.lovegender',
                'u.minage',
                'u.maxage',
                'u.connection',
                "(GLength(
                        LineStringFromWKB(
                            LineString(
                                u.coordinates,
                                GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                            )
                        )
                    ) * 100) distance"
            ))
            ->andHaving('age BETWEEN :minage AND :maxage')
            ->andWhere('u.id <> :id')
            ->orderBy('distance', 'ASC')
            ->setParameters(array(
                'minage' => $user->getMinage(),
                'maxage' => $user->getMaxage(),
                'id' => $user->getId()
            ))
            ->getQuery()
            ->getResult();
    }
}
