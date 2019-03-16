<?php

namespace App\Repository;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

use CrEOF\Spatial\Tests\Fixtures\LineStringEntity;
use CrEOF\Spatial\PHP\Types\Geometry\LineString;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use CrEOF\Spatial\Tests\OrmTestCase;
use Doctrine\ORM\Query;
use function GuzzleHttp\json_encode;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
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

    public function loadUserByUsername($usernameOrEmail)
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :query OR u.email = :query')
            ->setParameter('query', $usernameOrEmail)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findeOneUser(int $id, User $user)
    {
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
                // 'u.lovegender',
                // 'u.minage',
                // 'u.maxage',
                // 'u.connection',
                'u.location',
                "(GLength(
                        LineStringFromWKB(
                            LineString(
                                u.coordinates,
                                GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                            )
                        )
                    ) * 100) distance"
            ))
            ->andWhere('u.id = :id')
            ->setParameters(array(
                'id' => $id
            ))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getUsersByDistance(User $user, int $ratio)
    {
        $latitude = $user->getCoordinates()->getLatitude();
        $longitude = $user->getCoordinates()->getLongitude();

        return $this->createQueryBuilder('u')
            ->select(array(
                'u.id',
                'u.username',
                'u.description',
                '(DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age',
                'u.location',
                "(GLength(
                        LineStringFromWKB(
                            LineString(
                                u.coordinates,
                                GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                            )
                        )
                    ) * 100) distance"
            ))
            ->andHaving('distance <= :ratio')
            ->andHaving('age BETWEEN :minage AND :maxage')
            ->andWhere($user->getLovegender() ? 'u.gender IN (:lovegender)' : 'u.gender <> :lovegender OR u.gender IS NULL')
            // ->andWhere('u.connection IN (:connection)')
            ->andWhere('u.id <> :id')
            ->andWhere("u.roles NOT LIKE '%ROLE_ADMIN%'")
            ->orderBy('distance', 'ASC')
            ->setParameters(array(
                'ratio' => $ratio,
                'minage' => $user->getMinage() ?: 18,
                'maxage' => $user->getMaxage() ?: 99,
                'id' => $user->getId(),
                'lovegender' => $user->getLovegender() ?: 1,
                // 'connection' => $user->getConnection()
            ))
            ->getQuery()
            ->getResult();
    }

    public function searchUsers(string $search, User $user): ? array
    {
        $latitude = $user->getCoordinates()->getLatitude();
        $longitude = $user->getCoordinates()->getLongitude();

        $dql = "SELECT u.id, u.username, u.description, (DATE_DIFF(CURRENT_DATE(), u.birthday) / 365) age, u.location,
                (GLength(
                        LineStringFromWKB(
                            LineString(
                                u.coordinates,
                                GeomFromText('Point(" . $longitude . " " . $latitude . ")')
                            )
                        )
                    ) * 100) distance
                FROM App:User u WHERE u.id IN
                (SELECT IDENTITY(t.user) FROM App:Tag t WHERE t.name LIKE '%$search%')
                AND u.roles NOT LIKE '%ROLE_ADMIN%'";
        $query = $this->getEntityManager()->createQuery($dql);

        return $query->getResult();
    }

    public function getMatchIndex(User $userA, User $userB)
    {
        $a = $b = [];
        $tagsA = $userA->getTags();
        $tagsB = $userB->getTags();

        foreach ($tagsA as $tag) {
            $a[$tag->getCategory()->getName()][] = $tag->getName();
        }
        foreach ($tagsB as $tag) {
            $b[$tag->getCategory()->getName()][] = $tag->getName();
        }

        $matches = 0;
        foreach ($a as $category => $tags) {
            foreach ($tags as $name) {
                if (isset($b[$category]) && in_array($name, $b[$category])) {
                    $matches++;
                }
            }
        }

        $matchIndexA = count($tagsA) ? $matches / count($tagsA) : 0;
        $matchIndexB = count($tagsB) ? $matches / count($tagsB) : 0;
        $matchIndex = max($matchIndexA, $matchIndexB);

        return round($matchIndex * 100, 1);
    }
}
